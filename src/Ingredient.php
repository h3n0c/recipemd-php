<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD;

final readonly class Ingredient
{
    public function __construct(
        public string $name,
        public ?Amount $amount = null,
        /** Link to a recipe for this ingredient, e.g. "shortcrust.md". */
        public ?string $link = null,
    ) {}

    public function scale(float $ratio): self
    {
        return new self($this->name, $this->amount?->scale($ratio), $this->link);
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'amount' => $this->amount?->toArray(), 'link' => $this->link];
    }
}
