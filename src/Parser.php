<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * RecipeMD parser (specification 2.4.0) built on league/commonmark.
 *
 * league/commonmark provides the CommonMark tree (blocks with line numbers);
 * the spec's parsing algorithm is then applied to it. Verbatim parts
 * (description, instructions, ingredient names) are copied from the source
 * text, as the reference implementation does.
 */
final class Parser
{
    private MarkdownParser $markdown;

    /** @var list<string> Source lines, index 1 = line 1 */
    private array $lines = [];

    /** @var list<AbstractBlock> */
    private array $blocks = [];

    private int $pos = 0;

    public function __construct()
    {
        $env = new Environment();
        $env->addExtension(new CommonMarkCoreExtension());
        $this->markdown = new MarkdownParser($env);
    }

    public function parse(string $source): Recipe
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $this->lines = array_merge([''], explode("\n", $source));
        $this->blocks = iterator_to_array($this->markdown->parse($source)->children(), false);
        $this->pos = 0;

        // 2. Title: first block, level-1 heading
        $first = $this->current();
        if (! $first instanceof Heading || $first->getLevel() !== 1) {
            throw new ParseException('A recipe must start with a level-1 heading.');
        }
        $title = $this->headingText($first);
        $this->pos++;

        // 3-5. Description: everything up to tags, yields or the first divider
        $descriptionStart = $first->getEndLine() + 1;
        while (($c = $this->current()) !== null && ! $this->isTagsOrYields($c) && ! $c instanceof ThematicBreak) {
            $this->pos++;
        }
        $boundary = $this->current();
        if ($boundary === null) {
            throw new ParseException('Missing "---" divider before the ingredients.');
        }
        $description = $this->rawRange($descriptionStart, $boundary->getStartLine() - 1);

        // 6. Tags and yields, in any order, at most once each
        $tags = null;
        $yields = null;
        while (($c = $this->current()) instanceof Paragraph && $this->isTagsOrYields($c)) {
            $inner = $c->firstChild();
            $text = $this->plainText($inner);
            if ($inner instanceof Strong) {
                if ($yields !== null) {
                    throw new ParseException('Yields may only be given once.');
                }
                $yields = array_map(function (string $part) {
                    $amount = Amount::parse($part);
                    if ($amount === null) {
                        throw new ParseException("Yield without a number: \"{$part}\"");
                    }

                    return $amount;
                }, $this->splitList($text));
            } else {
                if ($tags !== null) {
                    throw new ParseException('Tags may only be given once.');
                }
                $tags = $this->splitList($text);
            }
            $this->pos++;
        }

        // 7. Divider before the ingredients
        if (! $this->current() instanceof ThematicBreak) {
            throw new ParseException('Missing "---" divider before the ingredients.');
        }
        $this->pos++;

        // 8. Ingredients and ingredient groups
        $ingredients = [];
        $groups = [];
        while (($c = $this->current()) !== null) {
            if ($c instanceof Heading) {
                $groups = array_merge($groups, $this->parseGroups(-1));
            } elseif ($c instanceof ListBlock) {
                $ingredients = array_merge($ingredients, $this->parseIngredientList());
            } else {
                break;
            }
        }

        // 9-10. Instructions: everything after the second divider
        $instructions = null;
        $c = $this->current();
        if ($c instanceof ThematicBreak) {
            $instructions = $this->rawRange($c->getEndLine() + 1, count($this->lines) - 1);
        } elseif ($c !== null) {
            throw new ParseException('Missing "---" divider before the instructions.');
        }

        return new Recipe(
            title: $title,
            description: $description,
            tags: $tags ?? [],
            yields: $yields ?? [],
            ingredients: $ingredients,
            ingredientGroups: $groups,
            instructions: $instructions,
        );
    }

    /** @return list<IngredientGroup> */
    private function parseGroups(int $parentLevel): array
    {
        $groups = [];
        while (($c = $this->current()) instanceof Heading && $c->getLevel() > $parentLevel) {
            $this->pos++;
            $ingredients = [];
            while ($this->current() instanceof ListBlock) {
                $ingredients = array_merge($ingredients, $this->parseIngredientList());
            }
            $children = $this->parseGroups($c->getLevel());
            $groups[] = new IngredientGroup($this->headingText($c), $ingredients, $children);
        }

        return $groups;
    }

    /** @return list<Ingredient> */
    private function parseIngredientList(): array
    {
        $list = $this->current();
        $this->pos++;
        $ingredients = [];
        foreach ($list->children() as $item) {
            if ($item instanceof ListItem) {
                $ingredients[] = $this->parseIngredient($item);
            }
        }

        return $ingredients;
    }

    private function parseIngredient(ListItem $item): Ingredient
    {
        // Raw text of the list item, with each paragraph's last line right-trimmed
        $start = $item->getStartLine();
        $end = $item->getEndLine();
        $lines = array_slice($this->lines, $start, $end - $start + 1, true);
        foreach ($item->children() as $child) {
            if ($child instanceof Paragraph && isset($lines[$child->getEndLine()])) {
                $lines[$child->getEndLine()] = rtrim($lines[$child->getEndLine()]);
            }
        }
        $raw = implode("\n", $lines);
        $raw = ltrim((string) preg_replace('/^[ \t]*(?:[-+*]|\d{1,9}[.)])/', '', $raw, 1));

        $amount = null;
        $link = null;
        $first = $item->firstChild();

        if ($first instanceof Paragraph) {
            $inline = $first->firstChild();
            if ($inline instanceof Emphasis) {
                $amount = Amount::parse($this->plainText($inline));
                $raw = ltrim($this->stripLeadingEmphasis($raw));
                $inline = $inline->next();
            }

            // A lone link (whitespace aside) in a single paragraph: ingredient linked to a recipe
            $rest = [];
            for ($n = $inline; $n !== null; $n = $n->next()) {
                if (! ($n instanceof Text && trim($n->getLiteral()) === '')) {
                    $rest[] = $n;
                }
            }
            if (count($rest) === 1 && $rest[0] instanceof Link && $first->next() === null) {
                $link = $rest[0]->getUrl();

                return new Ingredient($this->requireName($this->plainText($rest[0])), $amount, $link);
            }
        }

        return new Ingredient($this->requireName(trim($raw)), $amount, $link);
    }

    private function requireName(string $name): string
    {
        if ($name === '') {
            throw new ParseException('Ingredient without a name.');
        }

        return $name;
    }

    /** Strips a leading "*…*" or "_…_". */
    private function stripLeadingEmphasis(string $raw): string
    {
        $delimiter = $raw[0] ?? '';
        if ($delimiter !== '*' && $delimiter !== '_') {
            return $raw;
        }
        $close = strpos($raw, $delimiter, 1);

        return $close === false ? $raw : substr($raw, $close + 1);
    }

    private function isTagsOrYields(AbstractBlock $block): bool
    {
        if (! $block instanceof Paragraph) {
            return false;
        }
        $only = $block->firstChild();

        return $only !== null && $only->next() === null
            && ($only instanceof Emphasis || $only instanceof Strong);
    }

    /**
     * Splits a comma-separated list, ignoring commas between two digits
     * ("1,5 Tassen" stays a single element).
     *
     * @return list<string>
     */
    private function splitList(string $text): array
    {
        $parts = [];
        $current = '';
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            $between = $i > 0 && $i < $len - 1 && ctype_digit($text[$i - 1]) && ctype_digit($text[$i + 1]);
            if ($ch === ',' && ! $between) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        $parts[] = trim($current);

        return $parts;
    }

    private function headingText(Heading $heading): string
    {
        $start = $heading->getStartLine();
        $line = $this->lines[$start] ?? '';
        if (preg_match('/^ {0,3}#{1,6}(?:[ \t]+|$)/', $line, $m)) {
            // ATX: strip opening and closing #
            $text = substr($line, strlen($m[0]));
            $text = (string) preg_replace('/(?:^|[ \t]+)#+[ \t]*$/', '', $text);

            return trim($text);
        }

        // Setext: every line except the underline
        return trim(implode("\n", array_map('trim', array_slice($this->lines, $start, $heading->getEndLine() - $start))));
    }

    /** Raw lines [$from, $to] without leading/trailing blank lines; null if empty. */
    private function rawRange(int $from, int $to): ?string
    {
        if ($to < $from) {
            return null;
        }
        $lines = array_slice($this->lines, $from, $to - $from + 1);
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim(end($lines)) === '') {
            array_pop($lines);
        }

        return $lines === [] ? null : rtrim(implode("\n", $lines));
    }

    private function plainText(?Node $node): string
    {
        if ($node === null) {
            return '';
        }
        if ($node instanceof Newline) {
            return ' ';
        }
        if ($node instanceof Code || $node instanceof AbstractStringContainer) {
            return $node->getLiteral();
        }
        $text = '';
        foreach ($node->children() as $child) {
            $text .= $this->plainText($child);
        }

        return $text;
    }

    private function current(): ?AbstractBlock
    {
        return $this->blocks[$this->pos] ?? null;
    }
}
