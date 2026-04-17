<?php

declare(strict_types=1);

namespace Neos\MetaData\DimensionSpacePointProvider;

use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;

/**
 * The global configuration/shema for custom asset metadata properties
 */
interface DimensionSpacePointProvider
{
    public function getDefaultDimensionSpacePoint(): MetaDataDimensionSpacePoint;

    public function getDimensionSpacePointChain(MetaDataDimensionSpacePoint $dimensionSpacePoint): MetaDataDimensionSpacePoints;

    // public function RENAMEisDimensionSpacePointValid(MetaDataDimensionSpacePoint $dimensionSpacePoint): bool;
}
