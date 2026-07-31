<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;

/**
 * Persistence for metadata property values.
 *
 * Implementations are deliberately dumb: they store and look up values by scope and must not implement
 * any resolution rules. In particular the order of the given dimension space points is meaningless to
 * them – the {@see MetaDataManager} decides which of the returned values wins.
 */
interface MetaDataStorage
{

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string|int|bool $propertyValue, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void;

    public function unsetMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void;

    /**
     * All values stored for the given property within the given scope, in no particular order.
     *
     * The keys are opaque handles identifying the dimension space point a value is stored for; they can
     * be compared with {@see MetaDataDimensionSpacePoint::$hash}. Scopes without a stored value are
     * absent from the result.
     *
     * @return array<string, string|int|bool>
     */
    public function getMetaDataPropertyValues(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoints|MetaDataGlobalScope $scope): array;

}
