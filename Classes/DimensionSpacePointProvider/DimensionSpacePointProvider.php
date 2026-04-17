<?php

declare(strict_types=1);

namespace Neos\MetaData\DimensionSpacePointProvider;

use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;

/**
 * Provider for global dimension configuration (usually from the Neos Content Repository)
 */
interface DimensionSpacePointProvider
{
    public function getDefaultDimensionSpacePoint(): MetaDataDimensionSpacePoint;

    public function getDimensionSpacePointChain(MetaDataDimensionSpacePoint $dimensionSpacePoint): MetaDataDimensionSpacePoints;

    public function isDimensionSpacePointValid(MetaDataDimensionSpacePoint $dimensionSpacePoint): bool;
}
