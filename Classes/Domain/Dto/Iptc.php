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
 * IPTC DataType
 * @see https://www.iptc.org/std/photometadata/specification/IPTC-PhotoMetadata
 */
class Iptc extends AbstractMetaDataDto
{
    protected $properties = [
        'City' => '',
        'Contact' => [],
        'CopyrightNotice' => '',
        'Country' => '',
        'CountryCode' => '',
        'CreationDate' => null,
        'Creator' => [],
        'CreatorTitle' => [],
        'CreditLine' => '',
        'DeprecatedCategories' => [],
        'Description' => '',
        'DescriptionWriter' => [],
        'DigitalCreationDate' => null,
        'Headline' => '',
        'Instructions' => '',
        'IntellectualGenres' => [],
        'JobId' => '',
        'Keywords' => [],
        'Source' => '',
        'State' => '',
        'SubjectCodes' => [],
        'Sublocation' => '',
        'Title' => ''
    ];

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->properties['City'];
    }

    /**
     * @return array
     */
    public function getContact()
    {
        return $this->properties['Contact'];
    }

    /**
     * @return string
     */
    public function getCopyrightNotice()
    {
        return $this->properties['CopyrightNotice'];
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
    public function getCountryCode()
    {
        return $this->properties['CountryCode'];
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return $this->properties['CreationDate'];
    }

    /**
     * @return array
     */
    public function getCreator()
    {
        return $this->properties['Creator'];
    }

    /**
     * @return array
     */
    public function getCreatorTitle()
    {
        return $this->properties['CreatorTitle'];
    }

    /**
     * @return string
     */
    public function getCreditLine()
    {
        return $this->properties['CreditLine'];
    }

    /**
     * @return array
     */
    public function getDeprecatedCategories()
    {
        return $this->properties['DeprecatedCategories'];
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->properties['Description'];
    }

    /**
     * @return array
     */
    public function getDescriptionWriter()
    {
        return $this->properties['DescriptionWriter'];
    }

    /**
     * @return \DateTime
     */
    public function getDigitalCreationDate()
    {
        return $this->properties['DigitalCreationDate'];
    }

    /**
     * @return string
     */
    public function getHeadline()
    {
        return $this->properties['Headline'];
    }

    /**
     * @return string
     */
    public function getInstructions()
    {
        return $this->properties['Instructions'];
    }

    /**
     * @return array
     */
    public function getIntellectualGenres()
    {
        return $this->properties['IntellectualGenres'];
    }

    /**
     * @return string
     */
    public function getJobId()
    {
        return $this->properties['JobId'];
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
    public function getSource()
    {
        return $this->properties['Source'];
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->properties['State'];
    }

    /**
     * @return array
     */
    public function getSubjectCodes()
    {
        return $this->properties['SubjectCodes'];
    }

    /**
     * @return string
     */
    public function getSublocation()
    {
        return $this->properties['Sublocation'];
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->properties['Title'];
    }
}
