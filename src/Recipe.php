<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD;

final readonly class Recipe
{
    /**
     * @param list<string> $tags
     * @param list<Amount> $yields
     * @param list<Ingredient> $ingredients
     * @param list<IngredientGroup> $ingredientGroups
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public array $tags = [],
        public array $yields = [],
        public array $ingredients = [],
        public array $ingredientGroups = [],
        public ?string $instructions = null,
    ) {}

    /** Multiplies every amount and yield by $ratio. */
    public function scale(float $ratio): self
    {
        return new self(
            $this->title,
            $this->description,
            $this->tags,
            array_map(fn (Amount $y) => $y->scale($ratio), $this->yields),
            array_map(fn (Ingredient $i) => $i->scale($ratio), $this->ingredients),
            array_map(fn (IngredientGroup $g) => $g->scale($ratio), $this->ingredientGroups),
            $this->instructions,
        );
    }

    /**
     * Scales the recipe to a target yield, e.g. scaleToYield(6, 'servings').
     * The unit must match one of the declared yields.
     */
    public function scaleToYield(float $target, ?string $unit = null): self
    {
        foreach ($this->yields as $yield) {
            if ($yield->unit === $unit && $yield->factor != 0.0) {
                return $this->scale($target / $yield->factor);
            }
        }

        throw new \InvalidArgumentException("No yield in \"{$unit}\" for \"{$this->title}\"");
    }

    /** @return list<Ingredient> All ingredients, groups included (e.g. for a shopping list). */
    public function allIngredients(): array
    {
        $all = $this->ingredients;
        foreach ($this->ingredientGroups as $group) {
            array_push($all, ...$group->allIngredients());
        }

        return $all;
    }

    /** Same shape as the JSON files of the official test cases. */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'yields' => array_map(fn (Amount $y) => $y->toArray(), $this->yields),
            'tags' => $this->tags,
            'ingredients' => array_map(fn (Ingredient $i) => $i->toArray(), $this->ingredients),
            'ingredient_groups' => array_map(fn (IngredientGroup $g) => $g->toArray(), $this->ingredientGroups),
            'instructions' => $this->instructions,
        ];
    }
}
