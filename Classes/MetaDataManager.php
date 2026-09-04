<?php

declare(strict_types=1);

namespace Neos\MetaData;

use InvalidArgumentException;
use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Domain\Dto\MetaDataAssetFilter;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValue;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValues;
use Neos\MetaData\Storage\MetaDataStorage;

/**
 * Central API to read and write metadata property values of assets.
 *
 * All resolution rules live here: which dimension space points are candidates for a value, which of the
 * stored values wins and where an inherited value stems from. The {@see MetaDataStorage} is a dumb
 * lookup by scope.
 *
 * Wherever a dimension space point can be passed, NULL means the default dimension space point
 * {@see DimensionSpacePointProvider::getDefaultDimensionSpacePoint()}.
 */
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

    /**
     * Sets the value of a single metadata property.
     *
     * The value is coerced to the type the property is defined for, so that callers which only ever
     * have strings – the command line, form input, Fusion – do not have to cast. A value that cannot be
     * interpreted as that type is rejected rather than silently turned into a wrong one,
     * see {@see MetaDataPropertyType::coerceForStorage()}.
     */
    public function setMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        mixed $value,
        ?MetaDataDimensionSpacePoint $dimensionSpacePoint = null,
    ): void {
        $propertyDefinition = $this->propertyDefinition($propertyName);

        // TODO: ACL
        $this->storage->setMetaDataPropertyValue(
            $assetReference,
            $propertyDefinition->name,
            $propertyDefinition->type->coerceForStorage($value),
            $this->writeScope($propertyDefinition, $dimensionSpacePoint),
        );
    }

    public function unsetMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        ?MetaDataDimensionSpacePoint $dimensionSpacePoint = null,
    ): void {
        $propertyDefinition = $this->propertyDefinition($propertyName);

        // TODO: ACL
        $this->storage->unsetMetaDataPropertyValue(
            $assetReference,
            $propertyDefinition->name,
            $this->writeScope($propertyDefinition, $dimensionSpacePoint),
        );
    }

    /**
     * The value of a single metadata property, as seen from the given dimension space point.
     *
     * The result carries the own and the inherited value side by side, see {@see MetaDataPropertyValue}.
     */
    public function getMetaDataPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyName|string $propertyName,
        ?MetaDataDimensionSpacePoint $dimensionSpacePoint = null,
    ): MetaDataPropertyValue {
        return $this->resolvePropertyValue(
            $assetReference,
            $this->propertyDefinition($propertyName),
            $dimensionSpacePoint,
        );
    }

    /**
     * The values of all defined metadata properties, as seen from the given dimension space point.
     *
     * Every defined property is contained in the result, properties without any stored value with an
     * empty {@see MetaDataPropertyValue}.
     */
    public function getMetaDataPropertyValues(
        MetaDataAssetReference $assetReference,
        ?MetaDataDimensionSpacePoint $dimensionSpacePoint = null,
    ): MetaDataPropertyValues {
        $propertyValues = MetaDataPropertyValues::createEmpty();
        foreach ($this->propertyDefinitions as $propertyDefinition) {
            $propertyValues = $propertyValues->with(
                $propertyDefinition->name,
                $this->resolvePropertyValue($assetReference, $propertyDefinition, $dimensionSpacePoint),
            );
        }
        return $propertyValues;
    }

    /**
     * References of all assets that have a matching metadata value, as seen from one dimension space
     * point.
     *
     * A value counts only if it is the one that {@see self::getMetaDataPropertyValue()} would return for
     * the filter's dimension space point, so the search agrees with what an editor working in that
     * dimension sees: an asset whose caption is inherited from a fallback dimension is found, one whose
     * inherited caption is overridden by a non matching value of its own is not.
     *
     * Properties with a global scope are matched on their shared value regardless of the dimension space
     * point, just like they are read regardless of it.
     *
     * The result is lazily streamed and each asset is contained at most once.
     *
     * NOTE: This returns {@see MetaDataAssetReference}s – the identity of an asset within its asset
     * source – not `Asset` objects. This package never touches the asset model.
     *
     * @return iterable<MetaDataAssetReference>
     */
    public function findAssets(MetaDataAssetFilter $filter): iterable
    {
        $localizedPropertyNames = [];
        $globalScopePropertyNames = [];
        foreach ($this->filteredPropertyDefinitions($filter->propertyNames) as $propertyDefinition) {
            if ($propertyDefinition->globalScope) {
                $globalScopePropertyNames[] = $propertyDefinition->name;
            } else {
                $localizedPropertyNames[] = $propertyDefinition->name;
            }
        }

        return $this->storage->findAssets(
            $filter->assetSourceId,
            $filter->searchTerm,
            MetaDataPropertyNames::create(...$localizedPropertyNames),
            $this->dimensionSpacePointProvider->getDimensionSpacePointChain(
                $this->validateDimensionSpacePoint($filter->dimensionSpacePoint)
            ),
            MetaDataPropertyNames::create(...$globalScopePropertyNames),
        );
    }

    // -----------------------

    /**
     * The definitions of the given property names, or all of them if no names are given.
     *
     * @return iterable<MetaDataPropertyDefinition>
     */
    private function filteredPropertyDefinitions(?MetaDataPropertyNames $propertyNames): iterable
    {
        if ($propertyNames === null) {
            return $this->propertyDefinitions;
        }
        return array_map($this->propertyDefinition(...), iterator_to_array($propertyNames));
    }

    /**
     * Resolves the own and the inherited value of a single property with one storage lookup
     */
    private function resolvePropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyDefinition $propertyDefinition,
        ?MetaDataDimensionSpacePoint $dimensionSpacePoint,
    ): MetaDataPropertyValue {
        // TODO: ACL
        if ($propertyDefinition->globalScope) {
            return $this->resolveGlobalPropertyValue($assetReference, $propertyDefinition);
        }

        $candidates = $this->dimensionSpacePointProvider->getDimensionSpacePointChain(
            $this->validateDimensionSpacePoint($dimensionSpacePoint)
        );
        $storedValues = $this->storage->getMetaDataPropertyValues($assetReference, $propertyDefinition->name, $candidates);
        if ($storedValues === []) {
            return MetaDataPropertyValue::createEmpty();
        }

        $ownValue = null;
        foreach ($candidates as $index => $candidate) {
            if (!array_key_exists($candidate->hash, $storedValues)) {
                continue;
            }
            $value = $propertyDefinition->type->fromStoredValue($storedValues[$candidate->hash]);
            // A value that cannot be interpreted as the configured type is treated like an absent one,
            // so that it neither surfaces nor shadows a fallback that is still readable
            if ($value === null) {
                continue;
            }
            // The first candidate is the dimension space point that was asked for, all others are fallbacks
            if ($index === 0) {
                $ownValue = $value;
                continue;
            }
            return MetaDataPropertyValue::create($ownValue, $value, $candidate);
        }
        return MetaDataPropertyValue::create($ownValue);
    }

    /**
     * A value of a global scope is shared by all dimensions, so it is never inherited
     */
    private function resolveGlobalPropertyValue(
        MetaDataAssetReference $assetReference,
        MetaDataPropertyDefinition $propertyDefinition,
    ): MetaDataPropertyValue {
        $storedValues = $this->storage->getMetaDataPropertyValues(
            $assetReference,
            $propertyDefinition->name,
            MetaDataGlobalScope::create(),
        );
        if ($storedValues === []) {
            return MetaDataPropertyValue::createEmpty();
        }
        return MetaDataPropertyValue::create($propertyDefinition->type->fromStoredValue(reset($storedValues)));
    }

    /**
     * The scope a value of the given property is written to.
     *
     * For properties of a global scope the dimension space point is ignored on purpose: callers pass the
     * dimension they are currently working in without having to know which properties are localized.
     */
    private function writeScope(
        MetaDataPropertyDefinition $propertyDefinition,
        ?MetaDataDimensionSpacePoint $dimensionSpacePoint,
    ): MetaDataDimensionSpacePoint|MetaDataGlobalScope {
        if ($propertyDefinition->globalScope) {
            return MetaDataGlobalScope::create();
        }
        return $this->validateDimensionSpacePoint($dimensionSpacePoint);
    }

    private function propertyDefinition(MetaDataPropertyName|string $propertyName): MetaDataPropertyDefinition
    {
        if (is_string($propertyName)) {
            $propertyName = MetaDataPropertyName::fromString($propertyName);
        }
        if (!$this->propertyDefinitions->include($propertyName)) {
            throw new InvalidArgumentException(sprintf('Metadata property "%s" is not defined', $propertyName), 1776278047);
        }
        return $this->propertyDefinitions->get($propertyName);
    }

    private function validateDimensionSpacePoint(?MetaDataDimensionSpacePoint $dimensionSpacePoint): MetaDataDimensionSpacePoint
    {
        if ($dimensionSpacePoint === null) {
            return $this->dimensionSpacePointProvider->getDefaultDimensionSpacePoint();
        }

        if (!$this->dimensionSpacePointProvider->isDimensionSpacePointValid($dimensionSpacePoint)) {
            throw new InvalidArgumentException(sprintf('Dimension Space Point "%s" is not configured', $dimensionSpacePoint), 1776279083);
        }
        return $dimensionSpacePoint;
    }

}
