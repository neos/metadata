<?php
namespace Neos\MetaData\Mapper;

/*
 * This file is part of the Neos.MetaData package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use Neos\MetaData\Domain\Collection\MetaDataCollection;
use TYPO3\Media\Domain\Model\Asset;
use Doctrine\Common\Collections\ArrayCollection;
use TYPO3\Eel\Utility as EelUtility;
use TYPO3\Media\Domain\Model\Tag;

class AssetModelMetaDataMapper implements MetaDataMapperInterface
{

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
     * @var array
     */
    protected $tagFirstLevelCache = [];

    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Persistence\Doctrine\PersistenceManager
     */
    protected $persistenceManager;

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

    public function initializeObject() {
        if ($this->defaultContextVariables === NULL) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->defaultEelContext);
        }
    }

    /**
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     */
    public function mapMetaData(Asset $asset, MetaDataCollection $metaDataCollection)
    {
        $contextVariables = array_merge($this->defaultContextVariables, $metaDataCollection->toArray());

        if (isset($this->metaDataMappingConfiguration['title'])) {
            $asset->setTitle((string)EelUtility::evaluateEelExpression($this->metaDataMappingConfiguration['title'], $this->eelEvaluator, $contextVariables));
        }

        if (isset($this->metaDataMappingConfiguration['caption'])) {
            $asset->setCaption((string)EelUtility::evaluateEelExpression($this->metaDataMappingConfiguration['caption'], $this->eelEvaluator, $contextVariables));
        }

        if (isset($this->metaDataMappingConfiguration['tags'])) {
            $tagLabels = EelUtility::evaluateEelExpression($this->metaDataMappingConfiguration['tags'], $this->eelEvaluator, $contextVariables);
            $tagLabels = array_unique($tagLabels);

            $tags = new ArrayCollection();
            foreach ($tagLabels as $tagLabel) {
                if(trim($tagLabel) !== '') {
                    $tags->add($this->getOrCreateTag(trim($tagLabel)));
                }
            }
            $asset->setTags($tags);
        }

        if (!$this->persistenceManager->isNewObject($asset)) {
            $this->assetRepository->update($asset);
        }
    }

    /**
     * @param string $label
     * @return Tag
     */
    protected function getOrCreateTag($label) {
        if(isset($this->tagFirstLevelCache[$label])) {
            return $this->tagFirstLevelCache[$label];
        }

        $tag = $this->tagRepository->findOneByLabel($label);

        if(!($tag instanceof Tag)) {
            $tag = new Tag($label);
            $this->tagRepository->add($tag);
        }

        $this->tagFirstLevelCache[$label] = $tag;

        return $tag;
    }
}