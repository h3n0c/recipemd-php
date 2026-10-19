<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD;

/**
 * Serializes a Recipe to valid RecipeMD Markdown.
 * Output round-trips through Parser: parse(write($r)) equals $r.
 */
final class Writer
{
    public function write(Recipe $recipe): string
    {
        $out = ['# '.$recipe->title, ''];

        if ($recipe->description !== null) {
            $out[] = $recipe->description;
            $out[] = '';
        }
        if ($recipe->tags !== []) {
            $out[] = '*'.implode(', ', $recipe->tags).'*';
            $out[] = '';
        }
        if ($recipe->yields !== []) {
            $out[] = '**'.implode(', ', array_map(fn (Amount $y) => (string) $y, $recipe->yields)).'**';
            $out[] = '';
        }

        $out[] = '---';
        $out[] = '';

        foreach ($recipe->ingredients as $ingredient) {
            $out[] = $this->ingredientLine($ingredient);
        }
        if ($recipe->ingredients !== []) {
            $out[] = '';
        }
        foreach ($recipe->ingredientGroups as $group) {
            $this->writeGroup($group, 2, $out);
        }

        if ($recipe->instructions !== null) {
            $out[] = '---';
            $out[] = '';
            $out[] = $recipe->instructions;
        }

        return rtrim(implode("\n", $out))."\n";
    }

    /** @param list<string> $out */
    private function writeGroup(IngredientGroup $group, int $level, array &$out): void
    {
        $out[] = str_repeat('#', min($level, 6)).' '.$group->title;
        $out[] = '';
        foreach ($group->ingredients as $ingredient) {
            $out[] = $this->ingredientLine($ingredient);
        }
        if ($group->ingredients !== []) {
            $out[] = '';
        }
        foreach ($group->ingredientGroups as $child) {
            $this->writeGroup($child, $level + 1, $out);
        }
    }

    private function ingredientLine(Ingredient $ingredient): string
    {
        $line = '-';
        if ($ingredient->amount !== null) {
            $line .= ' *'.$ingredient->amount.'*';
        }
        $name = $ingredient->link !== null
            ? '['.$ingredient->name.']('.$this->linkTarget($ingredient->link).')'
            : $ingredient->name;

        return $line.' '.$name;
    }

    private function linkTarget(string $url): string
    {
        return preg_match('/[\s()<>]/', $url) ? '<'.$url.'>' : $url;
    }
}
