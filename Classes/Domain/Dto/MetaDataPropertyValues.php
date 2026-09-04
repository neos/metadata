<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * The values of all defined metadata properties, as seen from one {@see MetaDataDimensionSpacePoint}
 * @implements IteratorAggregate<MetaDataPropertyName, MetaDataPropertyValue>
 */
final readonly class MetaDataPropertyValues implements IteratorAggregate {

    /**
     * @param array<string, MetaDataPropertyValue> $values
     */
    private function __construct(
        private array $values,
    ) {
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    public function with(MetaDataPropertyName $propertyName, MetaDataPropertyValue $value): self
    {
        return new self([...$this->values, $propertyName->value => $value]);
    }

    public function get(MetaDataPropertyName $propertyName): MetaDataPropertyValue
    {
        if (!array_key_exists($propertyName->value, $this->values)) {
            throw new InvalidArgumentException(sprintf('Metadata property "%s" is not defined', $propertyName), 1776278183);
        }
        return $this->values[$propertyName->value];
    }

    /**
     * The effective values by property name, e.g. for rendering
     *
     * @return array<string, string|int|bool|null>
     */
    public function toArray(): array
    {
        return array_map(static fn (MetaDataPropertyValue $value) => $value->value, $this->values);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->values as $propertyName => $value) {
            yield MetaDataPropertyName::fromString($propertyName) => $value;
        }
    }
}
