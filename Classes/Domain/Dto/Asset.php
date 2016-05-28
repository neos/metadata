<?php
namespace Neos\MetaData\Domain\Dto;

/*
 * This file is part of the Neos.MetaData package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Default meta data of an asset
 */
class Asset extends AbstractMetaDataDto
{
    /**
     * @var array
     */
    protected $properties = [
        'Caption' => '',
        'Collections' => [],
        'FileName' => '',
        'Identifier' => '',
        'Tags' => [],
        'Title' => '',
        'AssetObject' => null
    ];

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->properties['Identifier'];
    }

    /**
     * @return string
     */
    public function getCaption()
    {
        return $this->properties['Caption'];
    }

    /**
     * @return array
     */
    public function getCollections()
    {
        return $this->properties['Collections'];
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->properties['FileName'];
    }
    
    /**
     * @return array
     */
    public function getTags()
    {
        return $this->properties['Tags'];
    }
    
    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->properties['Title'];
    }

    /**
     * @return \TYPO3\Media\Domain\Model\AssetInterface
     */
    public function getAssetObject()
    {
        return $this->properties['AssetObject'];
    }
}