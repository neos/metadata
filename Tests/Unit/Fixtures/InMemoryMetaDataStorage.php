<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Fixtures;

use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Storage\MetaDataStorage;
use Neos\MetaData\Storage\MetaDataStorageMaintenance;
use Neos\MetaData\Storage\MetaDataStoredValue;

/**
 * In-memory {@see MetaDataStorage} for unit tests.
 *
 * Deliberately returns values in insertion order rather than in candidate order, so that tests fail if
 * the resolution logic relies on the storage to order anything.
 */
final class InMemoryMetaDataStorage implements MetaDataStorage, MetaDataStorageMaintenance
{
    private const GLOBAL_DIMENSION_HASH = 'global';

    /**
     * @var array<string, MetaDataStoredValue>
     */
    private array $rows = [];

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string|int|bool $propertyValue, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void
    {
        $storedValue = new MetaDataStoredValue(
            $assetReference,
            $propertyName,
            self::dimensionHash($scope),
            $scope instanceof MetaDataGlobalScope,
            $propertyValue,
        );
        $this->rows[self::key($storedValue)] = $storedValue;
    }

    public function unsetMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void
    {
        unset($this->rows[implode("\0", [$assetReference->assetSourceId, $assetReference->assetId, $propertyName->value, self::dimensionHash($scope)])]);
    }

    public function getMetaDataPropertyValues(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoints|MetaDataGlobalScope $scope): array
    {
        $dimensionHashes = $scope instanceof MetaDataGlobalScope
            ? [self::GLOBAL_DIMENSION_HASH]
            : $scope->map(static fn (MetaDataDimensionSpacePoint $dimensionSpacePoint) => $dimensionSpacePoint->hash);

        $values = [];
        foreach ($this->rows as $row) {
            if ($row->assetReference->assetSourceId !== $assetReference->assetSourceId
                || $row->assetReference->assetId !== $assetReference->assetId
                || !$row->propertyName->equals($propertyName->value)
                || !in_array($row->dimensionHash, $dimensionHashes, true)) {
                continue;
            }
            $values[$row->dimensionHash] = $row->value;
        }
        return $values;
    }

    public function findAllStoredValues(): iterable
    {
        return array_values($this->rows);
    }

    public function deleteStoredValues(MetaDataStoredValue ...$storedValues): int
    {
        $deleted = 0;
        foreach ($storedValues as $storedValue) {
            $key = self::key($storedValue);
            if (array_key_exists($key, $this->rows)) {
                unset($this->rows[$key]);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Test helper: adds a value for an arbitrary dimension hash, including ones that are not (or no
     * longer) configured
     */
    public function addRawValue(MetaDataAssetReference $assetReference, string $propertyName, string $dimensionHash, string|int|bool $value): void
    {
        $storedValue = new MetaDataStoredValue(
            $assetReference,
            MetaDataPropertyName::fromString($propertyName),
            $dimensionHash,
            $dimensionHash === self::GLOBAL_DIMENSION_HASH,
            $value,
        );
        $this->rows[self::key($storedValue)] = $storedValue;
    }

    /**
     * @return list<MetaDataStoredValue>
     */
    public function all(): array
    {
        return array_values($this->rows);
    }

    // -----------------------

    private static function dimensionHash(MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): string
    {
        return $scope instanceof MetaDataGlobalScope ? self::GLOBAL_DIMENSION_HASH : $scope->hash;
    }

    private static function key(MetaDataStoredValue $storedValue): string
    {
        return implode("\0", [
            $storedValue->assetReference->assetSourceId,
            $storedValue->assetReference->assetId,
            $storedValue->propertyName->value,
            $storedValue->dimensionHash,
        ]);
    }
}
