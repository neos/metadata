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
 * Exif DataType
 * See: http://www.exif.org/Exif2-2.PDF
 */
class Exif extends AbstractMetaDataDto
{

    /**
     * @var array
     */
    protected $properties = [
        'Aperture' => 0.0,
        'Artist' => '',
        'ColorSpace' => '',
        'Copyright' => '',
        'Description' => '',
        'ExposureTime' => '',
        'FocalLength' => 0,
        'GPSLatitude' => 0.0,
        'GPSLongitude' => 0.0,
        'ISOSpeedRatings' => 0,
        'ImageDescription' => '',
        'Make' => '',
        'Model' => '',
        'XResolution' => 0,
        'YResolution' => 0
    ];

    /**
     * @return string
     */
    public function getArtist()
    {
        return $this->properties['Artist'];
    }

    /**
     * @return string
     */
    public function getColorSpace()
    {
        return $this->properties['ColorSpace'];
    }
    
    /**
     * @return string
     */
    public function getCopyright()
    {
        return $this->properties['Copyright'];
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->properties['Description'];
    }

    /**
     * @return string
     */
    public function getExposureTime()
    {
        return $this->properties['ExposureTime'];
    }

    /**
     * @return integer
     */
    public function getFocalLength()
    {
        return $this->properties['FocalLength'];
    }

    /**
     * @return float
     */
    public function getAperture()
    {
        return $this->properties['Aperture'];
    }

    /**
     * @return string
     */
    public function getImageDescription()
    {
        return $this->properties['ImageDescription'];
    }
    
    /**
     * @return float
     */
    public function getGPSLatitude()
    {
        return $this->properties['GPSLatitude'];
    }

    /**
     * @return float
     */
    public function getGPSLongitude()
    {
        return $this->properties['GPSLongitude'];
    }

    /**
     * @return integer
     */
    public function getIsoSpeedRatings()
    {
        return $this->properties['ISOSpeedRatings'];
    }

    /**
     * @return string
     */
    public function getMake()
    {
        return $this->properties['Make'];
    }

    /**
     * @return string
     */
    public function getModel()
    {
        return $this->properties['Model'];
    }

    /**
     * @return string
     */
    public function getXResolution()
    {
        return $this->properties['XResolution'];
    }

    /**
     * @return string
     */
    public function getYResolution()
    {
        return $this->properties['YResolution'];
    }
}