<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * Name of a custom asset metadata property
 */
final readonly class MetaDataPropertyName {

    public function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
