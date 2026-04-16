<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use IteratorAggregate;
use Traversable;

/**
 * A set of {@see MetaDataDimensionSpacePoint}s.
 * @implements IteratorAggregate<MetaDataDimensionSpacePoint>
 */
final readonly class MetaDataDimensionSpacePointSet implements IteratorAggregate{

    /**
     * @param list<MetaDataDimensionSpacePoint> $spacePoints
     */
    private function __construct(
        private array $spacePoints,
    ) {
    }

    public static function create(MetaDataDimensionSpacePoint ...$spacePoints): self
    {
        return new self(array_values($spacePoints));
    }

    public function include(MetaDataDimensionSpacePoint $dimensionSpacePoint): bool
    {
        foreach ($this->spacePoints as $spacePoint) {
            if ($spacePoint->equals($dimensionSpacePoint)) {
                return true;
            }
        }
        return false;
    }

    public function getIterator(): Traversable
    {
        yield from $this->spacePoints;
    }
}
