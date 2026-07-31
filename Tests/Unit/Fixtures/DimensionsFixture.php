<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Fixtures;

use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;

/**
 * A {@see DimensionSpacePointProvider} whose fallback chains are stated explicitly, so that tests of the
 * resolution logic do not depend on how chains are derived from content dimension presets.
 */
final class DimensionsFixture implements DimensionSpacePointProvider
{
    /**
     * @param array<string, MetaDataDimensionSpacePoints> $chainsByHash
     */
    private function __construct(
        private readonly MetaDataDimensionSpacePoint $defaultDimensionSpacePoint,
        private readonly MetaDataDimensionSpacePoints $dimensionSpacePoints,
        private readonly array $chainsByHash,
    ) {
    }

    /**
     * A single "language" dimension with the fallback chains de -> en, fr -> en and en
     */
    public static function languages(): self
    {
        $de = self::language('de');
        $en = self::language('en');
        $fr = self::language('fr');
        return new self(
            $en,
            MetaDataDimensionSpacePoints::create($en, $de, $fr),
            [
                $en->hash => MetaDataDimensionSpacePoints::create($en),
                $de->hash => MetaDataDimensionSpacePoints::create($de, $en),
                $fr->hash => MetaDataDimensionSpacePoints::create($fr, $en),
            ],
        );
    }

    /**
     * No content dimensions at all: the only valid dimension space point is the empty one
     */
    public static function none(): self
    {
        $empty = MetaDataDimensionSpacePoint::fromCoordinates([]);
        return new self($empty, MetaDataDimensionSpacePoints::create($empty), [$empty->hash => MetaDataDimensionSpacePoints::create($empty)]);
    }

    public static function language(string $value): MetaDataDimensionSpacePoint
    {
        return MetaDataDimensionSpacePoint::fromCoordinates(['language' => $value]);
    }

    public function getDimensionSpacePoints(): MetaDataDimensionSpacePoints
    {
        return $this->dimensionSpacePoints;
    }

    public function getDefaultDimensionSpacePoint(): MetaDataDimensionSpacePoint
    {
        return $this->defaultDimensionSpacePoint;
    }

    public function getDimensionSpacePointChain(MetaDataDimensionSpacePoint $dimensionSpacePoint): MetaDataDimensionSpacePoints
    {
        return $this->chainsByHash[$dimensionSpacePoint->hash] ?? MetaDataDimensionSpacePoints::create($dimensionSpacePoint);
    }

    public function isDimensionSpacePointValid(MetaDataDimensionSpacePoint $dimensionSpacePoint): bool
    {
        return $this->dimensionSpacePoints->include($dimensionSpacePoint);
    }
}
