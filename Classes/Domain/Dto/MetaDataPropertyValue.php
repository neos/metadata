<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * Value of a custom asset metadata property
 */
final class MetaDataPropertyValue {

    public function __construct(
        public string|int|bool $value,
    ) {
    }

    public static function parse(bool|int|string $simpleType): self
    {
        return new self($simpleType);
    }
}
