<?php

declare(strict_types=1);

namespace Neos\MetaData;

use InvalidArgumentException;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataConfiguration;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValues;
use Neos\MetaData\Storage\MetaDataStorage;

final readonly class MetaDataManager
{
    public function __construct(
        private MetaDataConfiguration $configuration,
        private MetaDataStorage $storage,
    ) {
    }

    public function getPropertyDefinitions(): MetaDataPropertyDefinitions
    {
        return $this->configuration->propertyDefinitions;
    }

    public function setMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        string|int|bool $value,
        MetaDataDimensionSpacePoint|array|null $dimensionSpacePoint = null,
    ): void {
        $propertyName = $this->validatePropertyName($propertyName);
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);

        // TODO: ACL, convert value according to property definition
        $this->storage->setMetaDataPropertyValue($assetReference, $propertyName, $value, $dimensionSpacePoint);
    }

    public function unsetMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        MetaDataDimensionSpacePoint|array|null $dimensionSpacePoint = null,
    ): void {
        $propertyName = $this->validatePropertyName($propertyName);
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);

        // TODO: ACL
        $this->storage->unsetMetaDataPropertyValue($assetReference, $propertyName, $dimensionSpacePoint);
    }

    public function getMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        MetaDataDimensionSpacePoint|array|null $dimensionSpacePoint = null,
    ): string|int|bool|null {
        $propertyName = $this->validatePropertyName($propertyName);
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);

        // TODO: ACL, convert value according to property definition
        return $this->storage->getMetaDataPropertyValue($assetReference, $propertyName, $dimensionSpacePoint);
    }

    public function getMetaDataPropertyValues(
        MetaDataAssetReference $assetReference,
        MetaDataDimensionSpacePoint|array|null $dimensionSpacePoint = null
    ): MetaDataPropertyValues {
        $propertyValues = MetaDataPropertyValues::createEmpty();
        $dimensionSpacePoint = $this->validateDimensionSpacePoint($dimensionSpacePoint);

        // TODO: ACL, convert values according to property definition
        foreach ($this->configuration->propertyDefinitions as $propertyDefinition) {
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
        if (!$this->configuration->propertyDefinitions->include($propertyName)) {
            throw new InvalidArgumentException(sprintf('Metadata property "%s" is not defined', $propertyName), 1776278047);
        }
        return $propertyName;
    }

    private function validateDimensionSpacePoint(MetaDataDimensionSpacePoint|array|null $dimensionSpacePoint): MetaDataDimensionSpacePoint
    {
        if ($dimensionSpacePoint === null) {
            return $this->configuration->defaultDimensionSpacePoint;
        }
        if (is_array($dimensionSpacePoint)) {
            $dimensionSpacePoint = MetaDataDimensionSpacePoint::fromCoordinates($dimensionSpacePoint);
        }
        // FIXME
//        if (!$this->configuration->dimensions->include($dimensionSpacePoint)) {
//            throw new InvalidArgumentException(sprintf('Dimension Space Point "%s" is not configured', $dimensionSpacePoint), 1776279083);
//        }
        return $dimensionSpacePoint;
    }

}
