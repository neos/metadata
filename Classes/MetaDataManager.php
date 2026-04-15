<?php

declare(strict_types=1);

namespace Neos\MetaData;

use Neos\MetaData\Domain\Dto\MetaDataConfiguration;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValue;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValues;
use Neos\MetaData\Storage\MetaDataStorage;

final readonly class MetaDataManager
{
    public function __construct(
        private MetaDataConfiguration $configuration,
        private MetaDataStorage $storage,
    ) {
    }

    public function setMetaDataPropertyValue(
        string $assetId,
        MetaDataPropertyName $propertyName,
        MetaDataPropertyValue $value,
        MetaDataDimensionSpacePoint|null $dimensionSpacePoint = null,
    ): void {
        if ($dimensionSpacePoint === null) {
            $dimensionSpacePoint = $this->configuration->defaultDimensionSpacePoint;
        }

        // TODO: Validate, ACL
        $this->storage->setMetaDataPropertyValue($assetId, $propertyName, $value, $dimensionSpacePoint);
    }

    public function unsetMetaDataPropertyValue(
        string $assetId,
        MetaDataPropertyName $propertyName,
        MetaDataDimensionSpacePoint|null $dimensionSpacePoint = null,
    ): void {
        if ($dimensionSpacePoint === null) {
            $dimensionSpacePoint = $this->configuration->defaultDimensionSpacePoint;
        }
        // TODO: Validate, ACL
        $this->storage->unsetMetaDataPropertyValue($assetId, $propertyName, $dimensionSpacePoint);
    }

    public function getMetaDataPropertyValue(
        string $assetId,
        MetaDataPropertyName $propertyName,
        MetaDataDimensionSpacePoint|null $dimensionSpacePoint = null,
    ): MetaDataPropertyValue|null {
        if ($dimensionSpacePoint === null) {
            $dimensionSpacePoint = $this->configuration->defaultDimensionSpacePoint;
        }
        // TODO: Validate, ACL
        return $this->storage->getMetaDataPropertyValue($assetId, $propertyName, $dimensionSpacePoint);
    }

    public function getMetaDataPropertyValues(
        string $assetId,
        MetaDataDimensionSpacePoint|null $dimensionSpacePoint = null
    ): MetaDataPropertyValues {
        $propertyValues = MetaDataPropertyValues::createEmpty();
        foreach ($this->configuration->propertyDefinitions as $propertyDefinition) {
            $propertyValues = $propertyValues->with($propertyDefinition->name, $this->getMetaDataPropertyValue($assetId, $propertyDefinition->name, $dimensionSpacePoint));
        }
        return $propertyValues;
    }

}
