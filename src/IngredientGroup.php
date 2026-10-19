<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD;

final readonly class IngredientGroup
{
    /**
     * @param list<Ingredient> $ingredients
     * @param list<IngredientGroup> $ingredientGroups
     */
    public function __construct(
        public string $title,
        public array $ingredients = [],
        public array $ingredientGroups = [],
    ) {}

    public function scale(float $ratio): self
    {
        return new self(
            $this->title,
            array_map(fn (Ingredient $i) => $i->scale($ratio), $this->ingredients),
            array_map(fn (IngredientGroup $g) => $g->scale($ratio), $this->ingredientGroups),
        );
    }

    /** @return list<Ingredient> All ingredients, including nested groups. */
    public function allIngredients(): array
    {
        $all = $this->ingredients;
        foreach ($this->ingredientGroups as $group) {
            array_push($all, ...$group->allIngredients());
        }

        return $all;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'ingredients' => array_map(fn (Ingredient $i) => $i->toArray(), $this->ingredients),
            'ingredient_groups' => array_map(fn (IngredientGroup $g) => $g->toArray(), $this->ingredientGroups),
        ];
    }
}
