<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD\Tests;

use H3n0c\RecipeMD\Amount;
use H3n0c\RecipeMD\ParseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    public static function amounts(): iterable
    {
        yield 'integer' => ['3', 3.0, null];
        yield 'integer with unit' => ['20 g', 20.0, 'g'];
        yield 'unit without space' => ['5ml', 5.0, 'ml'];
        yield 'decimal point' => ['1.5 kg', 1.5, 'kg'];
        yield 'decimal comma' => ['1,5 l', 1.5, 'l'];
        yield 'leading decimal' => ['.5 teaspoon', 0.5, 'teaspoon'];
        yield 'proper fraction' => ['3/4 cup', 0.75, 'cup'];
        yield 'improper fraction' => ['1 1/2 c. à s.', 1.5, 'c. à s.'];
        yield 'vulgar fraction' => ['¼', 0.25, null];
        yield 'improper vulgar fraction' => ['2 ½ cups', 2.5, 'cups'];
        yield 'multi-word unit' => ['1 c. à c.', 1.0, 'c. à c.'];
        yield 'negative' => ['-2 °C', -2.0, '°C'];
    }

    #[DataProvider('amounts')]
    public function testParse(string $input, float $factor, ?string $unit): void
    {
        $amount = Amount::parse($input);

        self::assertNotNull($amount);
        self::assertEqualsWithDelta($factor, $amount->factor, 1e-9);
        self::assertSame($unit, $amount->unit);
    }

    public function testEmptyIsNull(): void
    {
        self::assertNull(Amount::parse('   '));
    }

    public function testUnitWithoutNumberIsInvalid(): void
    {
        $this->expectException(ParseException::class);
        Amount::parse('pincée');
    }

    public function testFormatting(): void
    {
        self::assertSame('1.5 c. à s.', (string) new Amount(1.5, 'c. à s.'));
        self::assertSame('3', (string) new Amount(3.0));
        self::assertSame('0.25', (new Amount(0.25))->formatFactor());
        self::assertSame('0.3333333333', (new Amount(1 / 3))->formatFactor());
    }

    public function testScale(): void
    {
        self::assertEqualsWithDelta(30.0, (new Amount(20, 'g'))->scale(1.5)->factor, 1e-9);
    }
}
