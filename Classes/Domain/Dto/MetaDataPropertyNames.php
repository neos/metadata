<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A set of {@see MetaDataPropertyName}s, e.g. the properties a search is restricted to
 * {@see MetaDataAssetFilter::$propertyNames}
 *
 * @implements IteratorAggregate<MetaDataPropertyName>
 */
final readonly class MetaDataPropertyNames implements IteratorAggregate, Countable
{
    /**
     * @param list<MetaDataPropertyName> $propertyNames
     */
    private function __construct(
        private array $propertyNames,
    ) {
    }

    public static function create(MetaDataPropertyName|string ...$propertyNames): self
    {
        return new self(array_values(array_map(
            static fn (MetaDataPropertyName|string $propertyName) => is_string($propertyName)
                ? MetaDataPropertyName::fromString($propertyName)
                : $propertyName,
            $propertyNames,
        )));
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    public function include(MetaDataPropertyName $propertyName): bool
    {
        foreach ($this->propertyNames as $existingPropertyName) {
            if ($existingPropertyName->equals($propertyName->value)) {
                return true;
            }
        }
        return false;
    }

    public function isEmpty(): bool
    {
        return $this->propertyNames === [];
    }

    /**
     * @template T
     * @param Closure(MetaDataPropertyName): T $callback
     * @return T[]
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->propertyNames);
    }

    public function getIterator(): Traversable
    {
        yield from $this->propertyNames;
    }

    public function count(): int
    {
        return count($this->propertyNames);
    }
}
