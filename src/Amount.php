<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD;

/**
 * A RecipeMD amount: a numeric factor and an optional unit.
 * e.g. "1 1/2 tbsp" => factor 1.5, unit "tbsp"
 */
final readonly class Amount
{
    private const VULGAR_FRACTIONS = [
        '½' => 1 / 2, '⅓' => 1 / 3, '⅔' => 2 / 3, '¼' => 1 / 4, '¾' => 3 / 4,
        '⅕' => 1 / 5, '⅖' => 2 / 5, '⅗' => 3 / 5, '⅘' => 4 / 5, '⅙' => 1 / 6,
        '⅚' => 5 / 6, '⅐' => 1 / 7, '⅛' => 1 / 8, '⅜' => 3 / 8, '⅝' => 5 / 8,
        '⅞' => 7 / 8, '⅑' => 1 / 9, '⅒' => 1 / 10, '↉' => 0.0,
    ];

    public function __construct(
        public float $factor,
        public ?string $unit = null,
    ) {}

    /**
     * "Parsing an Amount" algorithm from spec 2.4.0.
     * Returns null for an empty string; throws when a unit is present
     * without a number ("*pinch* salt" is invalid).
     */
    public static function parse(string $s): ?self
    {
        $s = ltrim($s);
        $negative = false;
        if (str_starts_with($s, '-')) {
            $negative = true;
            $s = ltrim(substr($s, 1));
        }

        $vulgar = implode('', array_keys(self::VULGAR_FRACTIONS));
        $value = null;
        $m = [];

        if (preg_match('/^(\d+)\s+(\d+)\s*\/\s*(\d+)/u', $s, $m) && (int) $m[3] !== 0) {
            $value = (int) $m[1] + (int) $m[2] / (int) $m[3];
        } elseif (preg_match('/^(\d+)\s*(['.$vulgar.'])/u', $s, $m)) {
            $value = (int) $m[1] + self::VULGAR_FRACTIONS[$m[2]];
        } elseif (preg_match('/^(\d+)\s*\/\s*(\d+)/u', $s, $m) && (int) $m[2] !== 0) {
            $value = (int) $m[1] / (int) $m[2];
        } elseif (preg_match('/^(['.$vulgar.'])/u', $s, $m)) {
            $value = self::VULGAR_FRACTIONS[$m[1]];
        } elseif (preg_match('/^(\d*)[.,](\d+)/u', $s, $m)) {
            $value = (float) (($m[1] === '' ? '0' : $m[1]).'.'.$m[2]);
        } elseif (preg_match('/^(\d+)/u', $s, $m)) {
            $value = (float) $m[1];
        }

        $unit = trim(isset($m[0]) && $value !== null ? substr($s, strlen($m[0])) : $s);
        $unit = $unit === '' ? null : $unit;

        if ($value === null) {
            if ($unit !== null) {
                throw new ParseException("Amount without a number: \"{$s}\"");
            }

            return null;
        }

        return new self($negative ? -$value : $value, $unit);
    }

    public function scale(float $ratio): self
    {
        return new self($this->factor * $ratio, $this->unit);
    }

    /** Factor as a stable string: "5", "1.5", "0.25". */
    public function formatFactor(int $precision = 10): string
    {
        $rounded = round($this->factor, $precision);
        if ($rounded == (int) $rounded) {
            return (string) (int) $rounded;
        }

        return rtrim(rtrim(number_format($rounded, $precision, '.', ''), '0'), '.');
    }

    public function __toString(): string
    {
        return $this->formatFactor().($this->unit !== null ? ' '.$this->unit : '');
    }

    /** @return array{factor: string, unit: ?string} */
    public function toArray(): array
    {
        return ['factor' => $this->formatFactor(), 'unit' => $this->unit];
    }
}
