<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD\Tests;

use H3n0c\RecipeMD\Parser;
use H3n0c\RecipeMD\Writer;
use PHPUnit\Framework\TestCase;

final class RecipeTest extends TestCase
{
    private const QUICHE = <<<'MD'
        # Quiche aux poireaux

        Une quiche simple.

        *plat, végétarien*

        **4 portions**

        ---

        - *1* [pâte brisée](pate-brisee.md)
        - *3* poireaux
        - sel

        ## Appareil

        - *20 cl* crème fraîche
        - *3* œufs

        ---

        Cuire 35 min à 180 °C.
        MD;

    public function testParsesFrenchRecipe(): void
    {
        $recipe = (new Parser())->parse(self::QUICHE);

        self::assertSame('Quiche aux poireaux', $recipe->title);
        self::assertSame(['plat', 'végétarien'], $recipe->tags);
        self::assertSame('pate-brisee.md', $recipe->ingredients[0]->link);
        self::assertSame('Appareil', $recipe->ingredientGroups[0]->title);
        self::assertSame('cl', $recipe->ingredientGroups[0]->ingredients[0]->amount?->unit);
        self::assertCount(5, $recipe->allIngredients());
    }

    public function testScaleToYield(): void
    {
        $recipe = (new Parser())->parse(self::QUICHE)->scaleToYield(6, 'portions');

        self::assertSame('6 portions', (string) $recipe->yields[0]);
        self::assertSame('4.5', $recipe->ingredients[1]->amount?->formatFactor());
        self::assertNull($recipe->ingredients[2]->amount);
        self::assertSame('30 cl', (string) $recipe->ingredientGroups[0]->ingredients[0]->amount);
    }

    public function testWriterOutput(): void
    {
        $markdown = (new Writer())->write((new Parser())->parse(self::QUICHE));

        self::assertStringContainsString("- *1* [pâte brisée](pate-brisee.md)\n", $markdown);
        self::assertStringContainsString("**4 portions**\n", $markdown);
        self::assertStringContainsString("## Appareil\n\n- *20 cl* crème fraîche\n", $markdown);
        self::assertStringEndsWith("Cuire 35 min à 180 °C.\n", $markdown);
    }
}
