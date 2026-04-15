<?php

declare(strict_types=1);

namespace Neos\MetaData\Command;

use InvalidArgumentException;
use JsonException;
use Neos\Flow\Cli\CommandController;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValue;
use Neos\MetaData\MetaDataManager;

final class AssetMetaDataCommandController extends CommandController
{

    public function __construct(
        private readonly MetaDataManager $metaDataManager,
    )
    {
        parent::__construct();
    }

    /**
     * Sets a metadata property for an asset to a specific value
     *
     * @param string $assetId ID of the asset to set the metadata property for
     * @param string $property name of the metadata property to set
     * @param string $value value of the metadata property
     * @param string|null $dimensionSpacePoint optional dimension space point to set the metadata property for as JSON (e.g. `'{"language": "de"}')
     */
    public function setCommand(string $assetId, string $property, string $value, string|null $dimensionSpacePoint = null): void
    {
        $dimensionSpacePointDecoded = $dimensionSpacePoint !== null ? self::parseDimensionSpacePoint($dimensionSpacePoint) : null;
        $this->metaDataManager->setMetaDataPropertyValue(
            $assetId,
            MetaDataPropertyName::fromString($property),
            MetaDataPropertyValue::parse($value),
            $dimensionSpacePointDecoded,
        );
        $message = sprintf('Metadata property "%s" of asset "%s" was set to "%s"', $property, $value, $assetId);
        if ($dimensionSpacePointDecoded !== null) {
            $message .= sprintf(' for dimension space point "%s"', $dimensionSpacePointDecoded->hash);
        }
        $this->outputLine("<success>$message</success>");
    }

    /**
     * Removes a metadata property for an asset
     *
     * @param string $assetId ID of the asset to unset the metadata property for
     * @param string $property name of the metadata property to unset
     * @param string|null $dimensionSpacePoint optional dimension space point to unset the metadata property for as JSON (e.g. `'{"language": "de"}')
     */
    public function unsetCommand(string $assetId, string $property, string|null $dimensionSpacePoint = null): void
    {
        $dimensionSpacePointDecoded = $dimensionSpacePoint !== null ? self::parseDimensionSpacePoint($dimensionSpacePoint) : null;
        $this->metaDataManager->unsetMetaDataPropertyValue(
            $assetId,
            MetaDataPropertyName::fromString($property),
            $dimensionSpacePointDecoded,
        );
        $message = sprintf('Metadata property "%s" of asset "%s" was unset', $property, $assetId);
        if ($dimensionSpacePointDecoded !== null) {
            $message .= sprintf(' for dimension space point "%s"', $dimensionSpacePointDecoded->hash);
        }
        $this->outputLine("<notify>$message</notify>");
    }

    /**
     * Lists all metadata properties for an asset
     *
     * @param string $assetId ID of the asset to unset the metadata property for
     * @param string|null $dimensionSpacePoint optional dimension space point to unset the metadata property for as JSON (e.g. `'{"language": "de"}')
     */
    public function listCommand(string $assetId, string|null $dimensionSpacePoint = null): void
    {
        $dimensionSpacePointDecoded = $dimensionSpacePoint !== null ? self::parseDimensionSpacePoint($dimensionSpacePoint) : null;
        $metaDataPropertyValues = $this->metaDataManager->getMetaDataPropertyValues(
            $assetId,
            $dimensionSpacePointDecoded,
        );
        $message = sprintf('Metadata properties of asset "%s"', $assetId);
        if ($dimensionSpacePointDecoded !== null) {
            $message .= sprintf(' for dimension space point "%s"', $dimensionSpacePointDecoded->hash);
        }
        $message .= ':';
        $this->outputLine($message);
        foreach ($metaDataPropertyValues as $propertyName => $propertyValue) {
            $this->outputLine('  <b>%s:</b> %s', [$propertyName->value, $propertyValue?->value ?? '-']);
        }
    }

    private static function parseDimensionSpacePoint(string $dimensionSpacePoint): MetaDataDimensionSpacePoint
    {
        try {
            return MetaDataDimensionSpacePoint::fromCoordinates(json_decode($dimensionSpacePoint, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to parse dimension space point: ' . $e->getMessage(), 1776274597, $e);
        }
    }

}
