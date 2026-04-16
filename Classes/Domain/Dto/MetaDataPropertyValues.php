<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use IteratorAggregate;
use Traversable;

/**
 * Value of a custom asset metadata property
 * @implements IteratorAggregate<MetaDataPropertyName, string|int|bool|null>
 */
final class MetaDataPropertyValues implements IteratorAggregate {

    /**
     * @param array<string, string|int|bool|null> $values
     */
    private function __construct(
        private array $values,
    ) {
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    public function with(MetaDataPropertyName $propertyName, string|int|bool|null $value): self
    {
        return new self([...$this->values, $propertyName->value => $value]);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->values as $propertyName => $value) {
            yield MetaDataPropertyName::fromString($propertyName) => $value;
        }
    }
}
