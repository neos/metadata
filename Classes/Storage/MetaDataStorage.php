<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;

/**
 * Persistence for metadata property values.
 *
 * Implementations are deliberately dumb: they must not invent any resolution rules of their own. Where
 * precedence between dimension space points matters, it is stated explicitly by the method in question
 * – see the individual docblocks below.
 */
interface MetaDataStorage
{

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string $propertyValue, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void;

    public function unsetMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void;

    /**
     * All values stored for the given property within the given scope, in no particular order.
     *
     * The order of the given dimension space points is meaningless here – the {@see MetaDataManager}
     * decides which of the returned values wins.
     *
     * The keys are opaque handles identifying the dimension space point a value is stored for; they can
     * be compared with {@see MetaDataDimensionSpacePoint::$hash}. Scopes without a stored value are
     * absent from the result.
     *
     * @return array<string, string>
     */
    public function getMetaDataPropertyValues(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoints|MetaDataGlobalScope $scope): array;

    /**
     * References of all assets that have a matching value for at least one of the given properties.
     *
     * Unlike {@see self::getMetaDataPropertyValues()} the order of $dimensionSpacePointChain *is*
     * meaningful: it runs from the most to the least specific dimension space point, and only the
     * closest stored value of a property counts. A value stored for a dimension space point further
     * down the chain must be ignored if the same property also has a value further up – it is shadowed
     * and never surfaces for the dimension that was asked for. This is not a resolution rule that
     * implementations get to choose; it is the ranking they are handed.
     *
     * Localized and global scope properties are given separately because their values live in different
     * scopes: the ones named in $localizedPropertyNames are looked up along the chain, the ones named in
     * $globalScopePropertyNames in the global scope, which no dimension space point applies to. Either
     * set may be empty. Values stored in the respective other scope – e.g. left behind after a change of
     * {@see MetaDataPropertyDefinition::$globalScope} – must not be matched.
     *
     * The search term matches if it is contained anywhere in a value, case insensitively. NULL matches
     * every stored value, i.e. every asset that has any value for the given properties at all. An asset
     * that matches several times is returned once.
     *
     * Implementations should stream rather than materialize the whole result, as it can cover every
     * asset that has metadata.
     *
     * @param string|null $assetSourceId NULL matches assets of every asset source
     * @param string|null $searchTerm NULL matches every stored value
     * @return iterable<MetaDataAssetReference> ordered by asset source id, then asset id
     */
    public function findAssets(
        ?string $assetSourceId,
        ?string $searchTerm,
        MetaDataPropertyNames $localizedPropertyNames,
        MetaDataDimensionSpacePoints $dimensionSpacePointChain,
        MetaDataPropertyNames $globalScopePropertyNames,
    ): iterable;

}
