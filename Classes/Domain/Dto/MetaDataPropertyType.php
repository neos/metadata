<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Type of a custom asset metadata property.
 *
 * Values are stored as strings, so this is also what turns a value into its stored representation and
 * back: {@see self::coerceForStorage()} on the way in, {@see self::fromStoredValue()} on the way out.
 * The concrete PHP type of a value is case-dependent - see the private `to*()` helpers below for what
 * each case accepts and returns. Callers elsewhere in the domain therefore type a logical value as
 * `mixed` rather than repeating a union of every case's type.
 *
 * The two directions are deliberately not equally strict. Writing rejects what it cannot interpret,
 * because a caller passing "abc" for an integer property has made a mistake that should not be
 * silently turned into 0. Reading cannot afford to throw – it meets values that were written before a
 * property was given its current type – so it yields NULL, and the property reads as if it had no value.
 */
enum MetaDataPropertyType
{
    case string;
    case integer;
    case boolean;
    case float;
    case array;
    case dateTime;

    /**
     * The given value in the representation it is stored as.
     *
     * Unambiguous conversions are applied, so that callers which only ever have strings – the command
     * line, form input, Fusion – do not have to cast: "42" is a valid integer, "true", "on" and "yes"
     * are a valid boolean, as are their negative counterparts, a JSON encoded string is a valid array
     * and an ISO 8601 string is a valid date and time. Anything else is rejected.
     *
     * @throws InvalidArgumentException if the value cannot be interpreted as this type
     */
    public function coerceForStorage(mixed $value): string
    {
        $coerced = $this->tryCoerce($value);
        if ($coerced === null) {
            throw new InvalidArgumentException(sprintf('Value %s cannot be interpreted as %s', json_encode($value), $this->name), 1785715201);
        }
        return match (true) {
            is_bool($coerced) => $coerced ? '1' : '0',
            is_array($coerced) => json_encode($coerced, JSON_THROW_ON_ERROR),
            $coerced instanceof DateTimeInterface => $coerced->format(DATE_ATOM),
            default => (string)$coerced,
        };
    }

    /**
     * The given stored value as this type, or NULL if it cannot be interpreted as one
     */
    public function fromStoredValue(string $value): mixed
    {
        return $this->tryCoerce($value);
    }

    // -----------------------

    private function tryCoerce(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($this) {
            self::string => self::toString($value),
            self::integer => self::toInteger($value),
            self::boolean => self::toBoolean($value),
            self::float => self::toFloat($value),
            self::array => self::toArray($value),
            self::dateTime => self::toDateTime($value),
        };
    }

    private static function toString(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return null;
    }

    private static function toInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return preg_match('/^-?\d+$/', $trimmed) === 1 ? (int)$trimmed : null;
    }

    private static function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return match ($value) {
                0 => false,
                1 => true,
                default => null,
            };
        }
        if (!is_string($value)) {
            return null;
        }
        return match (strtolower(trim($value))) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => null,
        };
    }

    private static function toFloat(mixed $value): ?float
    {
        if (is_float($value) && !is_nan($value) && !is_infinite($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float)$value;
        }
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return preg_match('/^-?\d+(\.\d+)?$/', $trimmed) === 1 ? (float)$trimmed : null;
    }

    private static function toArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private static function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (!is_string($value)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        if ($parsed !== false) {
            return $parsed;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
