<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use Stringable;

/**
 * Name of a custom asset metadata property
 */
final readonly class MetaDataPropertyName implements Stringable {

    public function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(string $propertyName): bool
    {
        return $this->value === $propertyName;
    }
}
