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

use Doctrine\Common\Collections\ArrayCollection;
use Neos\MetaData\Domain\Collection\MetaDataCollection;
use TYPO3\Media\Domain\Model\Asset;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Eel\Utility as EelUtility;
use TYPO3\Media\Domain\Model\Tag;


class MetaDataManager
{

    /**
     * @Flow\Inject(lazy=FALSE)
     * @var \TYPO3\Eel\CompilingEvaluator
     */
    protected $eelEvaluator;

    /**
     * The default context variables available inside Eel
     * @var array
     */
    protected $defaultContextVariables;

    /**
     * @Flow\Inject
     * @var \TYPO3\Media\Domain\Repository\AssetRepository
     */
    protected $assetRepository;

    /**
     * @Flow\InjectConfiguration(package="Neos.MetaData", path="metaDataMapping")
     * @var array
     */
    protected $metaDataMappingConfiguration;

    /**
     * @Flow\InjectConfiguration(package="Neos.MetaData", path="defaultEelContext")
     * @var array
     */
    protected $defaultEelContext;

    /**
     * @Flow\Inject
     * @var \TYPO3\Media\Domain\Repository\TagRepository
     */
    protected $tagRepository;

    /**
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     */
    public function updateMetaDataForAsset(Asset $asset, MetaDataCollection $metaDataCollection)
    {
        if ($this->defaultContextVariables === NULL) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->defaultEelContext);
        }

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
        $contextVariables = array_merge($this->defaultContextVariables, $metaDataCollection->toArray());

        if (isset($this->metaDataMappingConfiguration['title'])) {
            $asset->setTitle(EelUtility::evaluateEelExpression($this->metaDataMappingConfiguration['title'], $this->eelEvaluator, $contextVariables));
        }

        if (isset($this->metaDataMappingConfiguration['caption'])) {
            $asset->setCaption(EelUtility::evaluateEelExpression($this->metaDataMappingConfiguration['caption'], $this->eelEvaluator, $contextVariables));
        }

        if (isset($this->metaDataMappingConfiguration['tags'])) {
            $tagLabels = EelUtility::evaluateEelExpression($this->metaDataMappingConfiguration['tags'], $this->eelEvaluator, $contextVariables);

            $tags = new ArrayCollection();
            foreach ($tagLabels as $tagLabel) {
                $tags->add($this->getOrCreateTag($tagLabel));
            }
            $asset->setTags($tags);
        }
        
        $this->assetRepository->update($asset);
    }


    /**
     * @param string $label
     * @return Tag
     */
    protected function getOrCreateTag($label) {
        $tag = $this->tagRepository->findByLabel($label);

        if(!($tag instanceof Tag)) {
            $tag = new Tag($label);
            $this->tagRepository->add($tag);
        }

        return $tag;
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