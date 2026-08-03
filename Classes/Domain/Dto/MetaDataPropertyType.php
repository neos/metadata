<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use InvalidArgumentException;

/**
 * Type of a custom asset metadata property.
 *
 * Values are stored as strings, so this is also what turns a value into its stored representation and
 * back: {@see self::coerceForStorage()} on the way in, {@see self::fromStoredValue()} on the way out.
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

    /**
     * The given value in the representation it is stored as.
     *
     * Unambiguous conversions are applied, so that callers which only ever have strings – the command
     * line, form input, Fusion – do not have to cast: "42" is a valid integer, "true", "on" and "yes"
     * are a valid boolean, as are their negative counterparts. Anything else is rejected.
     *
     * @throws InvalidArgumentException if the value cannot be interpreted as this type
     */
    public function coerceForStorage(string|int|bool $value): string
    {
        $coerced = $this->tryCoerce($value);
        if ($coerced === null) {
            throw new InvalidArgumentException(sprintf('Value %s cannot be interpreted as %s', json_encode($value), $this->name), 1785715201);
        }
        return is_bool($coerced) ? ($coerced ? '1' : '0') : (string)$coerced;
    }

    /**
     * The given stored value as this type, or NULL if it cannot be interpreted as one
     */
    public function fromStoredValue(string|int|bool $value): string|int|bool|null
    {
        return $this->tryCoerce($value);
    }

    // -----------------------

    private function tryCoerce(string|int|bool $value): string|int|bool|null
    {
        return match ($this) {
            self::string => is_bool($value) ? ($value ? '1' : '0') : (string)$value,
            self::integer => self::toInteger($value),
            self::boolean => self::toBoolean($value),
        };
    }

    private static function toInteger(string|int|bool $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        $trimmed = trim($value);
        return preg_match('/^-?\d+$/', $trimmed) === 1 ? (int)$trimmed : null;
    }

    private static function toBoolean(string|int|bool $value): ?bool
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
        return match (strtolower(trim($value))) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => null,
        };
    }
}
