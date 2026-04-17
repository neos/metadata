<?php

declare(strict_types=1);

namespace Neos\MetaData;

use Neos\MetaData\Configuration\MetaDataConfigurationProvider;
use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Storage\MetaDataStorage;

final readonly class MetaDataManagerFactory
{
    public function __construct(
        private MetaDataStorage $metaDataStorageProvider,
        private DimensionSpacePointProvider $dimensionSpacePointProvider,
        private MetaDataConfigurationProvider $assetMetaDataConfigurationProvider,
    )
    {
    }

    public function create(): MetaDataManager
    {
        return new MetaDataManager(
            $this->dimensionSpacePointProvider,
            $this->assetMetaDataConfigurationProvider->getPropertyConfiguration(),
            $this->metaDataStorageProvider,
        );
    }
}
