<?php

declare(strict_types=1);

namespace Neos\MetaData;

use Neos\Flow\Annotations as Flow;
use Neos\MetaData\Configuration\MetaDataConfigurationProvider;
use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Storage\MetaDataStorage;

#[Flow\Scope('singleton')]
class MetaDataManagerFactory
{
    public function __construct(
        private readonly MetaDataStorage $metaDataStorageProvider,
        private readonly DimensionSpacePointProvider $dimensionSpacePointProvider,
        private readonly MetaDataConfigurationProvider $assetMetaDataConfigurationProvider,
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
