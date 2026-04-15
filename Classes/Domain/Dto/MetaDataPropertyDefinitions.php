<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<MetaDataPropertyDefinition>
 */
final readonly class MetaDataPropertyDefinitions implements IteratorAggregate {

    /**
     * @param array<MetaDataPropertyDefinition> $values
     */
    private function __construct(
        private array $values,
    ) {
    }

    public static function create(MetaDataPropertyDefinition ...$customAssetPropertyDefinitions): self
    {
        return new self($customAssetPropertyDefinitions);
    }

    public function getIterator(): Traversable
    {
        yield from $this->values;
    }
}
