<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Fixtures;

use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test doubles for the {@see DimensionSpacePointProvider}.
 *
 * The fallback chains are stated explicitly rather than derived from content dimension presets, so that
 * tests of the resolution logic do not depend on how a chain comes about. How presets turn into chains
 * is covered by the tests of the provider implementations.
 */
trait DimensionSpacePointProviderMocks
{
    /**
     * A single "language" dimension with the fallback chains de -> en, fr -> en and en, and en as the
     * default one
     */
    protected function createLanguageDimensions(): DimensionSpacePointProvider&MockObject
    {
        $en = self::language('en');
        $de = self::language('de');
        $fr = self::language('fr');
        return $this->createDimensions(
            $en,
            MetaDataDimensionSpacePoints::create($en, $de, $fr),
            [
                $en->hash => [$en],
                $de->hash => [$de, $en],
                $fr->hash => [$fr, $en],
            ],
        );
    }

    /**
     * No content dimensions at all: the only valid dimension space point is the empty one
     */
    protected function createEmptyDimensions(): DimensionSpacePointProvider&MockObject
    {
        $empty = MetaDataDimensionSpacePoint::fromCoordinates([]);
        return $this->createDimensions($empty, MetaDataDimensionSpacePoints::create($empty), [$empty->hash => [$empty]]);
    }

    protected static function language(string $value): MetaDataDimensionSpacePoint
    {
        return MetaDataDimensionSpacePoint::fromCoordinates(['language' => $value]);
    }

    /**
     * @param array<string, list<MetaDataDimensionSpacePoint>> $chainsByHash ordered from the most to the least specific
     */
    private function createDimensions(
        MetaDataDimensionSpacePoint $defaultDimensionSpacePoint,
        MetaDataDimensionSpacePoints $dimensionSpacePoints,
        array $chainsByHash,
    ): DimensionSpacePointProvider&MockObject {
        $provider = $this->createMock(DimensionSpacePointProvider::class);
        $provider->method('getDimensionSpacePoints')->willReturn($dimensionSpacePoints);
        $provider->method('getDefaultDimensionSpacePoint')->willReturn($defaultDimensionSpacePoint);
        $provider->method('isDimensionSpacePointValid')->willReturnCallback(
            static fn (MetaDataDimensionSpacePoint $dimensionSpacePoint) => array_key_exists($dimensionSpacePoint->hash, $chainsByHash)
        );
        $provider->method('getDimensionSpacePointChain')->willReturnCallback(
            static fn (MetaDataDimensionSpacePoint $dimensionSpacePoint) => MetaDataDimensionSpacePoints::create(
                ...($chainsByHash[$dimensionSpacePoint->hash] ?? [$dimensionSpacePoint])
            )
        );
        return $provider;
    }
}
