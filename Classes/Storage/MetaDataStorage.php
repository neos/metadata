<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;

interface MetaDataStorage
{

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string|int|bool $propertyValue, MetaDataDimensionSpacePoint $dimensionSpacePoint): void;

    public function unsetMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): void;

    public function getMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): string|int|bool|null;

}
