<?php
namespace Neos\MetaData;

/*
 * This file is part of the Neos.MetaData package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\Asset;
use Neos\MetaData\Domain\Collection\MetaDataCollection;
use Neos\MetaData\Mapper\AssetModelMetaDataMapper;

/**
 * @Flow\Scope("singleton")
 */
class MetaDataManager
{
    /**
     * @Flow\Inject
     * @var AssetModelMetaDataMapper
     */
    protected $assetModelMetaDataMapper;

    /**
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     */
    public function updateMetaDataForAsset(Asset $asset, MetaDataCollection $metaDataCollection)
    {
        $this->assetModelMetaDataMapper->mapMetaData($asset, $metaDataCollection);
        $this->emitMetaDataCollectionUpdated($asset, $metaDataCollection);
    }

    /**
     * @Flow\Signal
     *
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     */
    public function emitMetaDataCollectionUpdated(Asset $asset, MetaDataCollection $metaDataCollection)
    {
    }
}
