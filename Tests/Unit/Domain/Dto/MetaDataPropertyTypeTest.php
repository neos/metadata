<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Domain\Dto;

use DateTimeImmutable;
use InvalidArgumentException;
use Neos\Flow\Tests\UnitTestCase;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;
use stdClass;

class MetaDataPropertyTypeTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{type: MetaDataPropertyType, value: mixed, expected: string}>
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

        yield 'float from float' => ['type' => MetaDataPropertyType::float, 'value' => 4.2, 'expected' => '4.2'];
        yield 'float from integer' => ['type' => MetaDataPropertyType::float, 'value' => 4, 'expected' => '4'];
        yield 'float from numeric string' => ['type' => MetaDataPropertyType::float, 'value' => '4.2', 'expected' => '4.2'];
        yield 'float from padded string' => ['type' => MetaDataPropertyType::float, 'value' => ' 4.2 ', 'expected' => '4.2'];
        yield 'negative float' => ['type' => MetaDataPropertyType::float, 'value' => '-4.2', 'expected' => '-4.2'];

        yield 'array from array' => ['type' => MetaDataPropertyType::array, 'value' => ['a', 'b'], 'expected' => '["a","b"]'];
        yield 'array from empty array' => ['type' => MetaDataPropertyType::array, 'value' => [], 'expected' => '[]'];
        yield 'array from JSON string' => ['type' => MetaDataPropertyType::array, 'value' => '["a","b"]', 'expected' => '["a","b"]'];
        yield 'array from nested array' => ['type' => MetaDataPropertyType::array, 'value' => ['a' => ['b' => 1]], 'expected' => '{"a":{"b":1}}'];

        yield 'dateTime from DateTimeImmutable' => ['type' => MetaDataPropertyType::dateTime, 'value' => new DateTimeImmutable('2024-01-02T10:00:00+00:00'), 'expected' => '2024-01-02T10:00:00+00:00'];
        yield 'dateTime from ISO 8601 string' => ['type' => MetaDataPropertyType::dateTime, 'value' => '2024-01-02T10:00:00+00:00', 'expected' => '2024-01-02T10:00:00+00:00'];
        yield 'dateTime from date only string' => ['type' => MetaDataPropertyType::dateTime, 'value' => '2024-01-02', 'expected' => (new DateTimeImmutable('2024-01-02'))->format(DATE_ATOM)];
    }

    /**
     * @dataProvider coercibleValues
     * @test
     */
    public function valuesAreCoercedToTheirStoredRepresentation(MetaDataPropertyType $type, mixed $value, string $expected): void
    {
        self::assertSame($expected, $type->coerceForStorage($value));
    }

    /**
     * @return iterable<string, array{type: MetaDataPropertyType, value: mixed}>
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

        yield 'float from words' => ['type' => MetaDataPropertyType::float, 'value' => 'abc'];
        yield 'float from empty string' => ['type' => MetaDataPropertyType::float, 'value' => ''];
        yield 'float from scientific notation' => ['type' => MetaDataPropertyType::float, 'value' => '1e10'];
        yield 'float from NAN' => ['type' => MetaDataPropertyType::float, 'value' => NAN];
        yield 'float from array' => ['type' => MetaDataPropertyType::float, 'value' => [1.0]];

        yield 'array from JSON scalar' => ['type' => MetaDataPropertyType::array, 'value' => '42'];
        yield 'array from malformed JSON' => ['type' => MetaDataPropertyType::array, 'value' => '{not json'];
        yield 'array from boolean' => ['type' => MetaDataPropertyType::array, 'value' => true];

        yield 'dateTime from unparseable string' => ['type' => MetaDataPropertyType::dateTime, 'value' => 'not a date'];
        yield 'dateTime from wrong object type' => ['type' => MetaDataPropertyType::dateTime, 'value' => new stdClass()];
        yield 'dateTime from integer' => ['type' => MetaDataPropertyType::dateTime, 'value' => 42];

        yield 'string from array' => ['type' => MetaDataPropertyType::string, 'value' => ['a']];
        yield 'string from object' => ['type' => MetaDataPropertyType::string, 'value' => new stdClass()];

        yield 'null is always rejected (string)' => ['type' => MetaDataPropertyType::string, 'value' => null];
        yield 'null is always rejected (integer)' => ['type' => MetaDataPropertyType::integer, 'value' => null];
        yield 'null is always rejected (array)' => ['type' => MetaDataPropertyType::array, 'value' => null];
    }

    /**
     * @dataProvider incoercibleValues
     * @test
     */
    public function valuesThatCannotBeInterpretedAreRejected(MetaDataPropertyType $type, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1785715201);
        $type->coerceForStorage($value);
    }

    /**
     * @return iterable<string, array{type: MetaDataPropertyType, value: string, expected: mixed}>
     */
    public static function storedValues(): iterable
    {
        yield 'string' => ['type' => MetaDataPropertyType::string, 'value' => 'A cat', 'expected' => 'A cat'];
        yield 'integer' => ['type' => MetaDataPropertyType::integer, 'value' => '42', 'expected' => 42];
        yield 'negative integer' => ['type' => MetaDataPropertyType::integer, 'value' => '-42', 'expected' => -42];
        yield 'true' => ['type' => MetaDataPropertyType::boolean, 'value' => '1', 'expected' => true];
        yield 'false' => ['type' => MetaDataPropertyType::boolean, 'value' => '0', 'expected' => false];
        yield 'float' => ['type' => MetaDataPropertyType::float, 'value' => '4.2', 'expected' => 4.2];
        yield 'negative float' => ['type' => MetaDataPropertyType::float, 'value' => '-4.2', 'expected' => -4.2];
        yield 'array' => ['type' => MetaDataPropertyType::array, 'value' => '["a","b"]', 'expected' => ['a', 'b']];
        yield 'empty array' => ['type' => MetaDataPropertyType::array, 'value' => '[]', 'expected' => []];
        yield 'dateTime' => ['type' => MetaDataPropertyType::dateTime, 'value' => '2024-01-02T10:00:00+00:00', 'expected' => new DateTimeImmutable('2024-01-02T10:00:00+00:00')];
    }

    /**
     * @dataProvider storedValues
     * @test
     */
    public function storedValuesAreReadBackAsTheirType(MetaDataPropertyType $type, string $value, mixed $expected): void
    {
        self::assertEquals($expected, $type->fromStoredValue($value));
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
        self::assertNull(MetaDataPropertyType::float->fromStoredValue('abc'));
        self::assertNull(MetaDataPropertyType::array->fromStoredValue('{not json'));
        self::assertNull(MetaDataPropertyType::dateTime->fromStoredValue('not a date'));
        self::assertSame('42', MetaDataPropertyType::string->fromStoredValue('42'), 'anything is readable as a string');
    }

    /**
     * @test
     */
    public function dateTimeIsAlwaysReadAsAnImmutableInstance(): void
    {
        self::assertInstanceOf(DateTimeImmutable::class, MetaDataPropertyType::dateTime->fromStoredValue('2024-01-02T10:00:00+00:00'));
    }
}
