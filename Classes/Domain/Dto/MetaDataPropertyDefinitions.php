<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<MetaDataPropertyDefinition>
 */
final readonly class MetaDataPropertyDefinitions implements IteratorAggregate {

    /**
     * @param array<string, MetaDataPropertyDefinition> $propertyDefinitionsByName
     */
    private function __construct(
        private array $propertyDefinitionsByName,
    ) {
    }

    public static function create(MetaDataPropertyDefinition ...$propertyDefinitions): self
    {
        $propertyDefinitionsByName = [];
        foreach ($propertyDefinitions as $propertyDefinition) {
            $propertyDefinitionsByName[$propertyDefinition->name->value] = $propertyDefinition;
        }
        return new self($propertyDefinitionsByName);
    }

    public function include(MetaDataPropertyName $propertyName): bool
    {
        return array_key_exists($propertyName->value, $this->propertyDefinitionsByName);
    }

    public function get(MetaDataPropertyName $propertyName): MetaDataPropertyDefinition
    {
        if (!array_key_exists($propertyName->value, $this->propertyDefinitionsByName)) {
            throw new InvalidArgumentException(sprintf('Metadata property "%s" is not defined', $propertyName), 1776278182);
        }
        return $this->propertyDefinitionsByName[$propertyName->value];
    }

    public function getIterator(): Traversable
    {
        yield from $this->propertyDefinitionsByName;
    }
}
