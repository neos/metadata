<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * The value of a single metadata property, as seen from one {@see MetaDataDimensionSpacePoint}.
 *
 * Carries the own and the inherited value side by side so that all three use cases are served by a
 * single read:
 *
 * - editing a value:      {@see self::$ownValue} – no shine-through, so an editor does not mistake an
 *                         inherited value for one that is set in the current dimension
 * - translation hint:     {@see self::$inheritedValue} together with {@see self::$inheritedFrom}
 * - rendering to visitor: {@see self::$value}
 *
 * For properties with a global scope {@see MetaDataPropertyDefinition::$globalScope} the value is
 * shared by all dimensions, so it is never inherited: {@see self::$inheritedValue} and
 * {@see self::$inheritedFrom} are always NULL.
 */
final readonly class MetaDataPropertyValue
{
    /**
     * @param string|int|bool|null $value the effective value, i.e. the own value falling back to the inherited one
     * @param string|int|bool|null $ownValue the value stored for the dimension space point that was asked for
     * @param string|int|bool|null $inheritedValue the value stored for the closest fallback dimension space point
     * @param MetaDataDimensionSpacePoint|null $inheritedFrom the dimension space point the inherited value stems from
     */
    private function __construct(
        public string|int|bool|null $value,
        public string|int|bool|null $ownValue,
        public string|int|bool|null $inheritedValue,
        public ?MetaDataDimensionSpacePoint $inheritedFrom,
    ) {
    }

    public static function create(
        string|int|bool|null $ownValue,
        string|int|bool|null $inheritedValue = null,
        ?MetaDataDimensionSpacePoint $inheritedFrom = null,
    ): self {
        return new self(
            $ownValue ?? $inheritedValue,
            $ownValue,
            $inheritedValue,
            $inheritedValue === null ? null : $inheritedFrom,
        );
    }

    public static function createEmpty(): self
    {
        return new self(null, null, null, null);
    }

    /**
     * Whether a value is stored for the dimension space point that was asked for, i.e. whether the
     * effective value is an override rather than an inherited one
     */
    public function hasOwnValue(): bool
    {
        return $this->ownValue !== null;
    }

    /** Fusion getter access */
    public function getOwnValue(): string|int|bool|null
    {
        return $this->ownValue;
    }

    /**
     * Whether the effective value stems from a fallback dimension space point
     */
    public function isInherited(): bool
    {
        return $this->ownValue === null && $this->inheritedValue !== null;
    }
}
