<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValue;

interface MetaDataStorage
{

    public function setMetaDataPropertyValue(string $assetId, MetaDataPropertyName $propertyName, MetaDataPropertyValue $propertyValue, MetaDataDimensionSpacePoint $dimensionSpacePoint): void;

    public function unsetMetaDataPropertyValue(string $assetId, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): void;

    public function getMetaDataPropertyValue(string $assetId, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): MetaDataPropertyValue|null;

}
