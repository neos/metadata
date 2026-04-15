<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * The global configuration/shema for custom asset metadata properties
 */
final readonly class MetaDataConfiguration
{
    public function __construct(
        public MetaDataDimensionSpacePoint $defaultDimensionSpacePoint,
        public MetaDataDimensionSpacePointSet $dimensions,
        public MetaDataPropertyDefinitions $propertyDefinitions,
    ) {
    }
}
