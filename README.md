# recipemd-php

A PHP implementation of [RecipeMD](https://recipemd.org) 2.4.0, the Markdown format for recipes.

- **Parser**: follows the specification's parsing algorithm and passes all official test cases.
- **Writer**: turns a `Recipe` back into valid RecipeMD (`parse(write($recipe))` round-trips).
- **Scaling**: multiply a recipe, or scale it to a target yield.

Built on [league/commonmark](https://commonmark.thephpleague.com), which Laravel already ships with.

## Installation

```bash
composer require h3n0c/recipemd
```

Requires PHP 8.2+.

## Usage

```php
use H3n0c\RecipeMD\Parser;
use H3n0c\RecipeMD\ParseException;
use H3n0c\RecipeMD\Writer;

try {
    $recipe = (new Parser())->parse(file_get_contents('guacamole.md'));
} catch (ParseException $e) {
    // not a valid RecipeMD document
}

$recipe->title;                 // "Guacamole"
$recipe->tags;                  // ["sauce", "vegan"]
$recipe->yields[0]->factor;     // 4.0
$recipe->yields[0]->unit;       // "Servings"

foreach ($recipe->allIngredients() as $ingredient) {
    $ingredient->name;          // "avocado"
    $ingredient->amount;        // Amount(1.0, null) or null
    $ingredient->link;          // "./other-recipe.md" for linked ingredients
}

$forEight = $recipe->scaleToYield(8, 'Servings');   // or $recipe->scale(2)

echo (new Writer())->write($forEight);
```

`Recipe::toArray()` returns the same structure as the JSON files of the official test suite.

### Amounts

`Amount::parse()` accepts every number format of the specification: integers, decimals with `.` or `,`, proper and improper fractions (`3/4`, `1 1/2`) and Unicode vulgar fractions (`½`, `2 ½`). Everything after the number is the unit, so multi-word units work too (`1 c. à s.`).

## Testing

```bash
composer install
composer test
```

`tests/fixtures/spec` holds the [official test cases](https://github.com/RecipeMD/RecipeMD/tree/master/testcases) (LGPL-3.0). The suite checks every valid case against its JSON, every invalid case for a `ParseException`, and the parser/writer round trip.

## License

MIT, see [LICENSE](LICENSE). The bundled test fixtures keep their own LGPL-3.0 license.
