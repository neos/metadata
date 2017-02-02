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
 * EXIF DataType
 * @see http://www.cipa.jp/std/documents/e/DC-008-Translation-2016-E.pdf
 */
class Exif extends AbstractMetaDataDto
{
    protected $properties = [
        'ImageWidth' => 0,
        'ImageLength' => 0,
        'BitsPerSample' => [0, 0, 0],
        'Compression' => '',
        'PhotometricInterpretation' => '',
        'Orientation' => '',
        'SamplesPerPixel' => 0,
        'PlanarConfiguration' => '',
        'YCbCrSubSampling' => '',
        'YCbCrPositioning' => '',
        'XResolution' => 0.0,
        'YResolution' => 0.0,
        'ResolutionUnit' => '',
        'DateTime' => null,
        'ImageDescription' => '',
        'Make' => '',
        'Model' => '',
        'Software' => '',
        'Artist' => '',
        'Copyright' => '',
        'ExifVersion' => '',
        'FlashpixVersion' => '',
        'ColorSpace' => '',
        'Gamma' => 0.0,
        'ComponentsConfiguration' => '',
        'CompressedBitsPerPixel' => 0.0,
        'PixelXDimension' => 0,
        'PixelYDimension' => 0,
        'MakerNote' => '',
        'UserComment' => '',
        'RelatedSoundFile' => '',
        'DateTimeOriginal' => null,
        'DateTimeDigitized' => null,
        'ExposureTime' => 0.0,
        'FNumber' => 0.0,
        'ExposureProgram' => '',
        'SpectralSensitivity' => '',
        'PhotographicSensitivity' => 0,
        'SensitivityType' => '',
        'StandardOutputSensitivity' => 0,
        'RecommendedExposureIndex' => 0,
        'ISOSpeed' => 0,
        'ISOSpeedLatitudeyyy' => 0,
        'ISOSpeedLatitudezzz' => 0,
        'ShutterSpeedValue' => 0.0,
        'ApertureValue' => 0.0,
        'BrightnessValue' => 0.0,
        'ExposureBiasValue' => 0.0,
        'MaxApertureValue' => 0.0,
        'SubjectDistance' => 0,
        'MeteringMode' => '',
        'LightSource' => '',
        'Flash' => '',
        'FocalLength' => 0.0,
        'SubjectArea' => [0, 0],
        'FlashEnergy' => 0.0,
        'FocalPlaneXResolution' => 0.0,
        'FocalPlaneYResolution' => 0.0,
        'FocalPlaneResolutionUnit' => '',
        'SubjectLocation' => [0, 0],
        'ExposureIndex' => 0.0,
        'SensingMethod' => '',
        'FileSource' => '',
        'SceneType' => '',
        'CustomRendered' => '',
        'ExposureMode' => '',
        'WhiteBalance' => '',
        'DigitalZoomRatio' => 0.0,
        'FocalLengthIn35mmFilm' => 0,
        'SceneCaptureType' => '',
        'GainControl' => '',
        'Contrast' => '',
        'Saturation' => '',
        'Sharpness' => '',
        'SubjectDistanceRange' => '',
        'Temperature' => 0.0,
        'Humidity' => 0.0,
        'Pressure' => 0.0,
        'WaterDepth' => 0.0,
        'Acceleration' => 0.0,
        'CameraElevationAngle' => 0.0,
        'ImageUniqueID' => '',
        'CameraOwnerName' => '',
        'BodySerialNumber' => '',
        'LensSpecification' => [
            0.0, // Minimum focal length (mm)
            0.0, // Maximum focal length (mm)
            0.0, // Minimum F number in the minimum focal length
            0.0 // Minimum F number in the maximum focal length
        ],
        'LensMake' => '',
        'LensModel' => '',
        'LensSerialNumber' => '',
        'GPSVersionID' => '',
        'GPSLatitude' => 0.0,
        'GPSLongitude' => 0.0,
        'GPSAltitude' => 0.0,
        'GPSSatellites' => '',
        'GPSStatus' => '',
        'GPSMeasureMode' => '',
        'GPSDOP' => 0.0,
        'GPSSpeedRef' => '',
        'GPSSpeed' => 0.0,
        'GPSTrackRef' => '',
        'GPSTrack' => 0.0,
        'GPSImgDirectionRef' => '',
        'GPSImgDirection' => 0.0,
        'GPSMapDatum' => '',
        'GPSDestLatitude' => 0.0,
        'GPSDestLongitude' => 0.0,
        'GPSDestBearingRef' => '',
        'GPSDestBearing' => 0.0,
        'GPSDestDistanceRef' => '',
        'GPSDestDistance' => 0.0,
        'GPSProcessingMethod' => '',
        'GPSAreaInformation' => '',
        'GPSDateTimeStamp' => null,
        'GPSDifferential' => '',
        'GPSHPositioningError' => 0.0,
    ];

    /**
     * @return int
     */
    public function getImageWidth()
    {
        return $this->properties['ImageWidth'];
    }

    /**
     * @return int
     */
    public function getImageLength()
    {
        return $this->properties['ImageLength'];
    }

    /**
     * @return array
     */
    public function getBitsPerSample()
    {
        return $this->properties['BitsPerSample'];
    }

    /**
     * @return string
     */
    public function getPhotometricInterpretation()
    {
        return $this->properties['PhotometricInterpretation'];
    }

    /**
     * @return string
     */
    public function getOrientation()
    {
        return $this->properties['Orientation'];
    }

    /**
     * @return int
     */
    public function getSamplesPerPixel()
    {
        return $this->properties['SamplesPerPixel'];
    }

    /**
     * @return string
     */
    public function getPlanarConfiguration()
    {
        return $this->properties['PlanarConfiguration'];
    }

    /**
     * @return string
     */
    public function getYCbCrSubSampling()
    {
        return $this->properties['YCbCrSubSampling'];
    }

    /**
     * @return string
     */
    public function getYCbCrPositioning()
    {
        return $this->properties['YCbCrPositioning'];
    }

    /**
     * @return float
     */
    public function getXResolution()
    {
        return $this->properties['XResolution'];
    }

    /**
     * @return float
     */
    public function getYResolution()
    {
        return $this->properties['YResolution'];
    }

    /**
     * @return string
     */
    public function getResolutionUnit()
    {
        return $this->properties['ResolutionUnit'];
    }

    /**
     * @return \DateTime
     */
    public function getDateTime()
    {
        return $this->properties['DateTime'];
    }

    /**
     * @return string
     */
    public function getImageDescription()
    {
        return $this->properties['ImageDescription'];
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
    public function getSoftware()
    {
        return $this->properties['Software'];
    }

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
    public function getCopyright()
    {
        return $this->properties['Copyright'];
    }

    /**
     * @return string
     */
    public function getExifVersion()
    {
        return $this->properties['ExifVersion'];
    }

    /**
     * @return string
     */
    public function getFlashpixVersion()
    {
        return $this->properties['FlashpixVersion'];
    }

    /**
     * @return string
     */
    public function getColorSpace()
    {
        return $this->properties['ColorSpace'];
    }

    /**
     * @return float
     */
    public function getGamma()
    {
        return $this->properties['Gamma'];
    }

    /**
     * @return string
     */
    public function getComponentsConfiguration()
    {
        return $this->properties['ComponentsConfiguration'];
    }

    /**
     * @return float
     */
    public function getCompressedBitsPerPixel()
    {
        return $this->properties['CompressedBitsPerPixel'];
    }

    /**
     * @return int
     */
    public function getPixelXDimension()
    {
        return $this->properties['PixelXDimension'];
    }

    /**
     * @return int
     */
    public function getPixelYDimension()
    {
        return $this->properties['PixelYDimension'];
    }

    /**
     * @return string
     */
    public function getMakerNote()
    {
        return $this->properties['MakerNote'];
    }

    /**
     * @return string
     */
    public function getUserComment()
    {
        return $this->properties['UserComment'];
    }

    /**
     * @return string
     */
    public function getRelatedSoundFile()
    {
        return $this->properties['RelatedSoundFile'];
    }

    /**
     * @return \DateTime
     */
    public function getDateTimeOriginal()
    {
        return $this->properties['DateTimeOriginal'];
    }

    /**
     * @return \DateTime
     */
    public function getDateTimeDigitized()
    {
        return $this->properties['DateTimeDigitized'];
    }

    /**
     * @return string
     */
    public function getSubSecTime()
    {
        return $this->properties['SubSecTime'];
    }

    /**
     * @return string
     */
    public function getSubSecTimeOriginal()
    {
        return $this->properties['SubSecTimeOriginal'];
    }

    /**
     * @return string
     */
    public function getSubSecTimeDigitized()
    {
        return $this->properties['SubSecTimeDigitized'];
    }

    /**
     * @return float
     */
    public function getExposureTime()
    {
        return $this->properties['ExposureTime'];
    }

    /**
     * @return float
     */
    public function getFNumber()
    {
        return $this->properties['FNumber'];
    }

    /**
     * @return string
     */
    public function getExposureProgram()
    {
        return $this->properties['ExposureProgram'];
    }

    /**
     * @return string
     */
    public function getSpectralSensitivity()
    {
        return $this->properties['SpectralSensitivity'];
    }

    /**
     * @return int
     */
    public function getPhotographicSensitivity()
    {
        return $this->properties['PhotographicSensitivity'];
    }

    /**
     * @return string
     */
    public function getSensitivityType()
    {
        return $this->properties['SensitivityType'];
    }

    /**
     * @return int
     */
    public function getStandardOutputSensitivity()
    {
        return $this->properties['StandardOutputSensitivity'];
    }

    /**
     * @return int
     */
    public function getRecommendedExposureIndex()
    {
        return $this->properties['RecommendedExposureIndex'];
    }

    /**
     * @return int
     */
    public function getISOSpeed()
    {
        return $this->properties['ISOSpeed'];
    }

    /**
     * @return int
     */
    public function getISOSpeedLatitudeyyy()
    {
        return $this->properties['ISOSpeedLatitudeyyy'];
    }

    /**
     * @return int
     */
    public function getISOSpeedLatitudezzz()
    {
        return $this->properties['ISOSpeedLatitudezzz'];
    }

    /**
     * @return float
     */
    public function getShutterSpeedValue()
    {
        return $this->properties['ShutterSpeedValue'];
    }

    /**
     * @return float
     */
    public function getApertureValue()
    {
        return $this->properties['ApertureValue'];
    }

    /**
     * @return float
     */
    public function getBrightnessValue()
    {
        return $this->properties['BrightnessValue'];
    }

    /**
     * @return float
     */
    public function getExposureBiasValue()
    {
        return $this->properties['ExposureBiasValue'];
    }

    /**
     * @return float
     */
    public function getMaxApertureValue()
    {
        return $this->properties['MaxApertureValue'];
    }

    /**
     * @return int
     */
    public function getSubjectDistance()
    {
        return $this->properties['SubjectDistance'];
    }

    /**
     * @return string
     */
    public function getMeteringMode()
    {
        return $this->properties['MeteringMode'];
    }

    /**
     * @return string
     */
    public function getLightSource()
    {
        return $this->properties['LightSource'];
    }

    /**
     * @return string
     */
    public function getFlash()
    {
        return $this->properties['Flash'];
    }

    /**
     * @return float
     */
    public function getFocalLength()
    {
        return $this->properties['FocalLength'];
    }

    /**
     * @return array
     */
    public function getSubjectArea()
    {
        return $this->properties['SubjectArea'];
    }

    /**
     * @return float
     */
    public function getFlashEnergy()
    {
        return $this->properties['FlashEnergy'];
    }

    /**
     * @return float
     */
    public function getFocalPlaneXResolution()
    {
        return $this->properties['FocalPlaneXResolution'];
    }

    /**
     * @return float
     */
    public function getFocalPlaneYResolution()
    {
        return $this->properties['FocalPlaneYResolution'];
    }

    /**
     * @return string
     */
    public function getFocalPlaneResolutionUnit()
    {
        return $this->properties['FocalPlaneResolutionUnit'];
    }

    /**
     * @return array
     */
    public function getSubjectLocation()
    {
        return $this->properties['SubjectLocation'];
    }

    /**
     * @return float
     */
    public function getExposureIndex()
    {
        return $this->properties['ExposureIndex'];
    }

    /**
     * @return int
     */
    public function get()
    {
        return $this->properties[''];
    }

    /**
     * @return string
     */
    public function getSensingMethod()
    {
        return $this->properties['SensingMethod'];
    }

    /**
     * @return string
     */
    public function getFileSource()
    {
        return $this->properties['FileSource'];
    }

    /**
     * @return string
     */
    public function getSceneType()
    {
        return $this->properties['SceneType'];
    }

    /**
     * @return string
     */
    public function getCustomRendered()
    {
        return $this->properties['CustomRendered'];
    }

    /**
     * @return string
     */
    public function getExposureMode()
    {
        return $this->properties['ExposureMode'];
    }

    /**
     * @return string
     */
    public function getWhiteBalance()
    {
        return $this->properties['WhiteBalance'];
    }

    /**
     * @return float
     */
    public function getDigitalZoomRatio()
    {
        return $this->properties['DigitalZoomRatio'];
    }

    /**
     * @return int
     */
    public function getFocalLengthIn35mmFilm()
    {
        return $this->properties['FocalLengthIn35mmFilm'];
    }

    /**
     * @return string
     */
    public function getSceneCaptureType()
    {
        return $this->properties['SceneCaptureType'];
    }

    /**
     * @return string
     */
    public function getGainControl()
    {
        return $this->properties['GainControl'];
    }

    /**
     * @return string
     */
    public function getContrast()
    {
        return $this->properties['Contrast'];
    }

    /**
     * @return string
     */
    public function getSaturation()
    {
        return $this->properties['Saturation'];
    }

    /**
     * @return string
     */
    public function getSharpness()
    {
        return $this->properties['Sharpness'];
    }

    /**
     * @return string
     */
    public function getSubjectDistanceRange()
    {
        return $this->properties['SubjectDistanceRange'];
    }

    /**
     * @return float
     */
    public function getTemperature()
    {
        return $this->properties['Temperature'];
    }

    /**
     * @return float
     */
    public function getHumidity()
    {
        return $this->properties['Humidity'];
    }

    /**
     * @return float
     */
    public function getPressure()
    {
        return $this->properties['Pressure'];
    }

    /**
     * @return float
     */
    public function getWaterDepth()
    {
        return $this->properties['WaterDepth'];
    }

    /**
     * @return float
     */
    public function getAcceleration()
    {
        return $this->properties['Acceleration'];
    }

    /**
     * @return float
     */
    public function getCameraElevationAngle()
    {
        return $this->properties['CameraElevationAngle'];
    }

    /**
     * @return string
     */
    public function getImageUniqueID()
    {
        return $this->properties['ImageUniqueID'];
    }

    /**
     * @return string
     */
    public function getCameraOwnerName()
    {
        return $this->properties['CameraOwnerName'];
    }

    /**
     * @return string
     */
    public function getBodySerialNumber()
    {
        return $this->properties['BodySerialNumber'];
    }

    /**
     * @return array<float>
     */
    public function getLensSpecification()
    {
        return $this->properties['LensSpecification'];
    }

    /**
     * @return string
     */
    public function getLensMake()
    {
        return $this->properties['LensMake'];
    }

    /**
     * @return string
     */
    public function getLensModel()
    {
        return $this->properties['LensModel'];
    }

    /**
     * @return string
     */
    public function getLensSerialNumber()
    {
        return $this->properties['LensSerialNumber'];
    }

    /**
     * @return string
     */
    public function getGPSVersionID()
    {
        return $this->properties['GPSVersionID'];
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
     * @return float
     */
    public function getGPSAltitude()
    {
        return $this->properties['GPSAltitude'];
    }

    /**
     * @return string
     */
    public function getGPSSatellites()
    {
        return $this->properties['GPSSatellites'];
    }

    /**
     * @return string
     */
    public function getGPSStatus()
    {
        return $this->properties['GPSStatus'];
    }

    /**
     * @return string
     */
    public function getGPSMeasureMode()
    {
        return $this->properties['GPSMeasureMode'];
    }

    /**
     * @return float
     */
    public function getGPSDOP()
    {
        return $this->properties['GPSDOP'];
    }

    /**
     * @return string
     */
    public function getGPSSpeedRef()
    {
        return $this->properties['GPSSpeedRef'];
    }

    /**
     * @return float
     */
    public function getGPSSpeed()
    {
        return $this->properties['GPSSpeed'];
    }

    /**
     * @return string
     */
    public function getGPSTrackRef()
    {
        return $this->properties['GPSTrackRef'];
    }

    /**
     * @return float
     */
    public function getGPSTrack()
    {
        return $this->properties['GPSTrack'];
    }

    /**
     * @return string
     */
    public function getGPSImgDirectionRef()
    {
        return $this->properties['GPSImgDirectionRef'];
    }

    /**
     * @return float
     */
    public function getGPSImgDirection()
    {
        return $this->properties['GPSImgDirection'];
    }

    /**
     * @return string
     */
    public function getGPSMapDatum()
    {
        return $this->properties['GPSMapDatum'];
    }

    /**
     * @return float
     */
    public function getGPSDestLatitude()
    {
        return $this->properties['GPSDestLatitude'];
    }

    /**
     * @return float
     */
    public function getGPSDestLongitude()
    {
        return $this->properties['GPSDestLongitude'];
    }

    /**
     * @return string
     */
    public function getGPSDestBearingRef()
    {
        return $this->properties['GPSDestBearingRef'];
    }

    /**
     * @return float
     */
    public function getGPSDestBearing()
    {
        return $this->properties['GPSDestBearing'];
    }

    /**
     * @return string
     */
    public function getGPSDestDistanceRef()
    {
        return $this->properties['GPSDestDistanceRef'];
    }

    /**
     * @return float
     */
    public function getGPSDestDistance()
    {
        return $this->properties['GPSDestDistance'];
    }

    /**
     * @return string
     */
    public function getGPSProcessingMethod()
    {
        return $this->properties['GPSProcessingMethod'];
    }

    /**
     * @return string
     */
    public function getGPSAreaInformation()
    {
        return $this->properties['GPSAreaInformation'];
    }

    /**
     * @return \DateTime
     */
    public function getGPSDateTimeStamp()
    {
        return $this->properties['GPSDateTimeStamp'];
    }

    /**
     * @return string
     */
    public function getGPSDifferential()
    {
        return $this->properties['GPSDifferential'];
    }

    /**
     * @return float
     */
    public function getGPSHPositioningError()
    {
        return $this->properties['GPSHPositioningError'];
    }
}
