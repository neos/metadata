<?php
namespace Neos\MetaData\Mapper;


interface MetaDataMapperInterface
{
    /**
     * @param \TYPO3\Media\Domain\Model\Asset $asset
     * @param \Neos\MetaData\Domain\Collection\MetaDataCollection $metaDataCollection
     * @return void
     */
    public function mapMetaData(\TYPO3\Media\Domain\Model\Asset $asset, \Neos\MetaData\Domain\Collection\MetaDataCollection $metaDataCollection);
}