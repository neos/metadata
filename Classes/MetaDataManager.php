<?php

declare(strict_types=1);

namespace Neos\MetaData;

use InvalidArgumentException;
use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValues;
use Neos\MetaData\Storage\MetaDataStorage;

final readonly class MetaDataManager
{
    public function __construct(
        private DimensionSpacePointProvider $dimensionSpacePointProvider,
        private MetaDataPropertyDefinitions $propertyDefinitions,
        private MetaDataStorage $storage,
    ) {
    }

    public function getPropertyDefinitions(): MetaDataPropertyDefinitions
    {
        return $this->propertyDefinitions;
    }

    public function getDimensionSpacePointConfiguration(): MetaDataDimensionSpacePoints
    {
        return $this->dimensionSpacePointProvider->getDimensionSpacePoints();
    }

    public function setMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        string|int|bool $value,
        MetaDataDimensionSpacePoint $dimensionSpacePoint,
    ): void {
        $propertyName = $this->validatePropertyName($propertyName);
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);

        // TODO: ACL, convert value according to property definition
        $this->storage->setMetaDataPropertyValue($assetReference, $propertyName, $value, $dimensionSpacePoint);
    }

    public function unsetMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        MetaDataDimensionSpacePoint $dimensionSpacePoint,
    ): void {
        $propertyName = $this->validatePropertyName($propertyName);
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);

        // TODO: ACL
        $this->storage->unsetMetaDataPropertyValue($assetReference, $propertyName, $dimensionSpacePoint);
    }

    public function getMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        MetaDataDimensionSpacePoint $dimensionSpacePoint,
    ): string|int|bool|null {
        $propertyName = $this->validatePropertyName($propertyName);
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);
        // Getter: we need to consider the whole dimension space point fallback chain
        $dimensionSpacePoints = $this->dimensionSpacePointProvider->getDimensionSpacePointChain($dimensionSpacePoint);

        // TODO: ACL, convert value according to property definition
        return $this->storage->getMetaDataPropertyValue($assetReference, $propertyName, $dimensionSpacePoints);
    }

    public function getMetaDataPropertyValues(
        MetaDataAssetReference $assetReference,
        MetaDataDimensionSpacePoint $dimensionSpacePoint,
    ): MetaDataPropertyValues {
        $propertyValues = MetaDataPropertyValues::createEmpty();
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);

        // TODO: ACL, convert values according to property definition
        foreach ($this->propertyDefinitions as $propertyDefinition) {
            $propertyValues = $propertyValues->with($propertyDefinition->name, $this->getMetaDataPropertyValue($assetReference, $propertyDefinition->name, $dimensionSpacePoint));
        }
        return $propertyValues;
    }

    // -----------------------

    private function validatePropertyName(MetaDataPropertyName|string $propertyName): MetaDataPropertyName
    {
        if (is_string($propertyName)) {
            $propertyName = MetaDataPropertyName::fromString($propertyName);
        }
        if (!$this->propertyDefinitions->include($propertyName)) {
            throw new InvalidArgumentException(sprintf('Metadata property "%s" is not defined', $propertyName), 1776278047);
        }
        return $propertyName;
    }

    private function validateDimensionSpacePoint(MetaDataDimensionSpacePoint $dimensionSpacePoint): MetaDataDimensionSpacePoint
    {
        if (!$this->dimensionSpacePointProvider->isDimensionSpacePointValid($dimensionSpacePoint)) {
            throw new InvalidArgumentException(sprintf('Dimension Space Point "%s" is not configured', $dimensionSpacePoint), 1776279083);
        }
        return $dimensionSpacePoint;
    }

}
