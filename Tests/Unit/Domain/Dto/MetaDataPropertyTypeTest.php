<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Domain\Dto;

use InvalidArgumentException;
use Neos\Flow\Tests\UnitTestCase;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;

class MetaDataPropertyTypeTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{type: MetaDataPropertyType, value: string|int|bool, expected: string}>
     */
    public static function coercibleValues(): iterable
    {
        yield 'string from string' => ['type' => MetaDataPropertyType::string, 'value' => 'A cat', 'expected' => 'A cat'];
        yield 'string from integer' => ['type' => MetaDataPropertyType::string, 'value' => 42, 'expected' => '42'];
        yield 'string from boolean' => ['type' => MetaDataPropertyType::string, 'value' => true, 'expected' => '1'];
        yield 'string is not trimmed' => ['type' => MetaDataPropertyType::string, 'value' => '  padded  ', 'expected' => '  padded  '];

        yield 'integer from integer' => ['type' => MetaDataPropertyType::integer, 'value' => 42, 'expected' => '42'];
        yield 'integer from numeric string' => ['type' => MetaDataPropertyType::integer, 'value' => '42', 'expected' => '42'];
        yield 'integer from padded string' => ['type' => MetaDataPropertyType::integer, 'value' => ' 42 ', 'expected' => '42'];
        yield 'negative integer' => ['type' => MetaDataPropertyType::integer, 'value' => '-42', 'expected' => '-42'];
        yield 'integer from boolean' => ['type' => MetaDataPropertyType::integer, 'value' => true, 'expected' => '1'];

        yield 'boolean from boolean' => ['type' => MetaDataPropertyType::boolean, 'value' => true, 'expected' => '1'];
        yield 'boolean from false' => ['type' => MetaDataPropertyType::boolean, 'value' => false, 'expected' => '0'];
        yield 'boolean from "true"' => ['type' => MetaDataPropertyType::boolean, 'value' => 'true', 'expected' => '1'];
        yield 'boolean from "TRUE"' => ['type' => MetaDataPropertyType::boolean, 'value' => 'TRUE', 'expected' => '1'];
        yield 'boolean from "on"' => ['type' => MetaDataPropertyType::boolean, 'value' => 'on', 'expected' => '1'];
        yield 'boolean from "yes"' => ['type' => MetaDataPropertyType::boolean, 'value' => 'yes', 'expected' => '1'];
        yield 'boolean from "false"' => ['type' => MetaDataPropertyType::boolean, 'value' => 'false', 'expected' => '0'];
        yield 'boolean from "no"' => ['type' => MetaDataPropertyType::boolean, 'value' => 'no', 'expected' => '0'];
        yield 'boolean from 1' => ['type' => MetaDataPropertyType::boolean, 'value' => 1, 'expected' => '1'];
        yield 'boolean from 0' => ['type' => MetaDataPropertyType::boolean, 'value' => 0, 'expected' => '0'];
    }

    /**
     * @dataProvider coercibleValues
     * @test
     */
    public function valuesAreCoercedToTheirStoredRepresentation(MetaDataPropertyType $type, string|int|bool $value, string $expected): void
    {
        self::assertSame($expected, $type->coerceForStorage($value));
    }

    /**
     * @return iterable<string, array{type: MetaDataPropertyType, value: string|int|bool}>
     */
    public static function incoercibleValues(): iterable
    {
        yield 'integer from words' => ['type' => MetaDataPropertyType::integer, 'value' => 'abc'];
        yield 'integer from empty string' => ['type' => MetaDataPropertyType::integer, 'value' => ''];
        yield 'integer from decimal' => ['type' => MetaDataPropertyType::integer, 'value' => '4.2'];
        yield 'integer from partially numeric' => ['type' => MetaDataPropertyType::integer, 'value' => '42px'];

        yield 'boolean from words' => ['type' => MetaDataPropertyType::boolean, 'value' => 'maybe'];
        yield 'boolean from empty string' => ['type' => MetaDataPropertyType::boolean, 'value' => ''];
        yield 'boolean from other integer' => ['type' => MetaDataPropertyType::boolean, 'value' => 2];
    }

    /**
     * @dataProvider incoercibleValues
     * @test
     */
    public function valuesThatCannotBeInterpretedAreRejected(MetaDataPropertyType $type, string|int|bool $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1785715201);
        $type->coerceForStorage($value);
    }

    /**
     * @return iterable<string, array{type: MetaDataPropertyType, value: string, expected: string|int|bool}>
     */
    public static function storedValues(): iterable
    {
        yield 'string' => ['type' => MetaDataPropertyType::string, 'value' => 'A cat', 'expected' => 'A cat'];
        yield 'integer' => ['type' => MetaDataPropertyType::integer, 'value' => '42', 'expected' => 42];
        yield 'negative integer' => ['type' => MetaDataPropertyType::integer, 'value' => '-42', 'expected' => -42];
        yield 'true' => ['type' => MetaDataPropertyType::boolean, 'value' => '1', 'expected' => true];
        yield 'false' => ['type' => MetaDataPropertyType::boolean, 'value' => '0', 'expected' => false];
    }

    /**
     * @dataProvider storedValues
     * @test
     */
    public function storedValuesAreReadBackAsTheirType(MetaDataPropertyType $type, string $value, string|int|bool $expected): void
    {
        self::assertSame($expected, $type->fromStoredValue($value));
    }

    /**
     * @test
     */
    public function everyCoercibleValueSurvivesTheRoundTrip(): void
    {
        foreach (self::coercibleValues() as $name => $case) {
            $stored = $case['type']->coerceForStorage($case['value']);
            self::assertNotNull($case['type']->fromStoredValue($stored), sprintf('"%s" is not readable again', $name));
        }
    }

    /**
     * Reading must not throw - it meets values that were written before a property was given its
     * current type
     *
     * @test
     */
    public function storedValuesThatCannotBeInterpretedAreReadAsNull(): void
    {
        self::assertNull(MetaDataPropertyType::integer->fromStoredValue('abc'));
        self::assertNull(MetaDataPropertyType::boolean->fromStoredValue('maybe'));
        self::assertSame('42', MetaDataPropertyType::string->fromStoredValue('42'), 'anything is readable as a string');
    }
}
