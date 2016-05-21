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

/*
 * IPTC DataType
 * See: https://www.iptc.org/std/photometadata/specification/IPTC-PhotoMetadata
 */
class Iptc extends AbstractMetaDataDto
{
    /**
     * @var array
     */
    protected $properties = [
        'AuthorByline' => '',
        'Category' => '',
        'City' => '',
        'Country' => '',
        'CreationDate' => null,
        'Description' => '',
        'Keywords' => [],
        'Title' => '',
        'State' => '',
        'SubCategories' => '',
        'SubLocation' => '',
    ];
    

    /**
     * @return string
     */
    public function getAuthorByLine()
    {
        return $this->properties['AuthorByline'];
    }
    
    /**
     * @return string
     */
    public function getCategory()
    {
        return $this->properties['Category'];
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->properties['City'];
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->properties['Country'];
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->properties['Description'];
    }
    
    /**
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return $this->properties['CreationDate'];
    }
    
    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->properties['Title'];
    }

    /**
     * @return array
     */
    public function getKeywords()
    {
        return $this->properties['Keywords'];
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->properties['State'];
    }

    /**
     * @return string
     */
    public function getSubCategories()
    {
        return $this->properties['SubCategories'];
    }

    /**
     * @return string
     */
    public function getSubLocation()
    {
        return $this->properties['SubLocation'];
    }
}