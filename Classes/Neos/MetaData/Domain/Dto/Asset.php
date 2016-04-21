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
        'caption' => '',
        'fileName' => '',
        'identifier' => '',
        'title' => ''
    ];

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->properties['identifier'];
    }

    /**
     * @return string
     */
    public function getCaption()
    {
        return $this->properties['caption'];
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->properties['fileName'];
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->properties['title'];
    }

}