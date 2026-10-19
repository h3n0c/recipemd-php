<?php

declare(strict_types=1);

namespace H3n0c\RecipeMD\Tests;

use H3n0c\RecipeMD\Parser;
use H3n0c\RecipeMD\ParseException;
use H3n0c\RecipeMD\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the official RecipeMD 2.4.0 test cases (tests/fixtures/spec).
 */
final class SpecTest extends TestCase
{
    private const FIXTURES = __DIR__.'/fixtures/spec';

    public static function validCases(): iterable
    {
        foreach (glob(self::FIXTURES.'/*.md') as $md) {
            if (! str_ends_with($md, '.invalid.md') && basename($md) !== 'README.md') {
                yield basename($md) => [$md];
            }
        }
    }

    public static function invalidCases(): iterable
    {
        foreach (glob(self::FIXTURES.'/*.invalid.md') as $md) {
            yield basename($md) => [$md];
        }
    }

    #[DataProvider('validCases')]
    public function testParsesValidCase(string $md): void
    {
        $expected = json_decode(file_get_contents(substr($md, 0, -3).'.json'), true);
        $actual = (new Parser())->parse(file_get_contents($md))->toArray();

        self::assertSame(self::normalize($expected), self::normalize($actual));
    }

    #[DataProvider('invalidCases')]
    public function testRejectsInvalidCase(string $md): void
    {
        $this->expectException(ParseException::class);
        (new Parser())->parse(file_get_contents($md));
    }

    #[DataProvider('validCases')]
    public function testWriterRoundTrip(string $md): void
    {
        $parser = new Parser();
        $recipe = $parser->parse(file_get_contents($md));
        $reparsed = $parser->parse((new Writer())->write($recipe));

        self::assertSame(self::normalize($recipe->toArray()), self::normalize($reparsed->toArray()));
    }

    /** Property order is ignored, as the specification requires. */
    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map(self::normalize(...), $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
