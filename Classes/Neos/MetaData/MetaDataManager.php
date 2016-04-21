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

use Neos\MetaData\Domain\Collection\MetaDataCollection;
use TYPO3\Media\Domain\Model\Asset;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Eel\Utility as EelUtility;


class MetaDataManager
{

    /**
     * @Flow\Inject(lazy=FALSE)
     * @var \TYPO3\Eel\CompilingEvaluator
     */
    protected $eelEvaluator;

    /**
     * @Flow\Inject
     * @var \TYPO3\Media\Domain\Repository\AssetRepository
     */
    protected $assetRepository;

    /**
     * @Flow\InjectConfiguration(package="Neos.MetaData", path="metaDataMapping")
     * @var array
     */
    protected $settings;


    /**
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     */
    public function updateMetaDataForAsset(Asset $asset, MetaDataCollection $metaDataCollection)
    {
        $this->mapMetaDataToAsset($asset, $metaDataCollection);
        $this->emitMetaDataCollectionUpdated($asset, $metaDataCollection);
    }

    /**
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     * @throws \TYPO3\Eel\Exception
     * @throws \TYPO3\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    protected function mapMetaDataToAsset(Asset $asset, MetaDataCollection $metaDataCollection)
    {
        if (isset($this->settings['title'])) {
            $asset->setTitle(EelUtility::evaluateEelExpression($this->settings['title'], $this->eelEvaluator, $metaDataCollection->toArray()));
        }

        if (isset($this->settings['caption'])) {
            $asset->setCaption(EelUtility::evaluateEelExpression($this->settings['caption'], $this->eelEvaluator, $metaDataCollection->toArray()));
        }

        $this->assetRepository->update($asset);
    }

    /**
     * @Flow\Signal
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     */
    public function emitMetaDataCollectionUpdated(Asset $asset, MetaDataCollection $metaDataCollection)
    {
    }
}