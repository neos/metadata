<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;

final readonly class MetaDataStorageProviderDbalAdapter implements MetaDataStorage, MetaDataStorageMaintenance
{
    private const TABLE_NAME = 'neos_metadata_value';

    /**
     * Dimension hash for values of a global scope. A real dimension hash is an MD5 hex string, so this
     * sentinel can never collide with one.
     */
    private const GLOBAL_DIMENSION_HASH = 'global';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string|int|bool $propertyValue, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void
    {
        $statement = sprintf(<<<MYSQL
            INSERT INTO %s
                (asset_source_id, asset_id, property_name, property_value, dimension_hash)
            VALUES
                (:assetSourceId, :assetId, :propertyName, :propertyValue, :dimensionHash)
            ON DUPLICATE KEY UPDATE property_value = :propertyValue
        MYSQL, self::TABLE_NAME);
        $this->connection->executeStatement(
            $statement,
            [
                'assetSourceId' => $assetReference->assetSourceId,
                'assetId' => $assetReference->assetId,
                'propertyName' => $propertyName->value,
                'propertyValue' => $propertyValue,
                'dimensionHash' => self::dimensionHash($scope),
            ]
        );
    }

    public function unsetMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void
    {
        $this->connection->delete(self::TABLE_NAME, [
            'asset_source_id' => $assetReference->assetSourceId,
            'asset_id' => $assetReference->assetId,
            'property_name' => $propertyName->value,
            'dimension_hash' => self::dimensionHash($scope),
        ]);
    }

    public function getMetaDataPropertyValues(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoints|MetaDataGlobalScope $scope): array
    {
        $dimensionHashes = self::dimensionHashes($scope);
        if ($dimensionHashes === []) {
            return [];
        }
        $query = $this->connection->createQueryBuilder();
        $query->select('dimension_hash', 'property_value')
            ->from(self::TABLE_NAME)
            ->where(
                $query->expr()->and(
                    $query->expr()->eq('asset_source_id', ':assetSourceId'),
                    $query->expr()->eq('asset_id', ':assetId'),
                    $query->expr()->eq('property_name', ':propertyName'),
                    $query->expr()->in('dimension_hash', ':dimensionHashes'),
                )
            )
            // NOTE: No ordering – which of the values wins is a domain decision that is made by the MetaDataManager
            ->setParameters([
                'assetSourceId' => $assetReference->assetSourceId,
                'assetId' => $assetReference->assetId,
                'propertyName' => $propertyName->value,
                'dimensionHashes' => $dimensionHashes,
            ], [
                'dimensionHashes' => ArrayParameterType::STRING,
            ]);

        $values = [];
        foreach ($query->executeQuery()->iterateAssociative() as $row) {
            $values[$row['dimension_hash']] = $row['property_value'];
        }
        return $values;
    }

    public function findAllStoredValues(): iterable
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('asset_source_id', 'asset_id', 'property_name', 'property_value', 'dimension_hash')
            ->from(self::TABLE_NAME);
        foreach ($query->executeQuery()->iterateAssociative() as $row) {
            yield new MetaDataStoredValue(
                MetaDataAssetReference::create($row['asset_source_id'], $row['asset_id']),
                MetaDataPropertyName::fromString($row['property_name']),
                $row['dimension_hash'],
                $row['dimension_hash'] === self::GLOBAL_DIMENSION_HASH,
                $row['property_value'],
            );
        }
    }

    public function deleteStoredValues(MetaDataStoredValue ...$storedValues): int
    {
        $deleted = 0;
        foreach ($storedValues as $storedValue) {
            $deleted += $this->connection->delete(self::TABLE_NAME, [
                'asset_source_id' => $storedValue->assetReference->assetSourceId,
                'asset_id' => $storedValue->assetReference->assetId,
                'property_name' => $storedValue->propertyName->value,
                'dimension_hash' => $storedValue->dimensionHash,
            ]);
        }
        return $deleted;
    }

    // -----------------------

    private static function dimensionHash(MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): string
    {
        return $scope instanceof MetaDataGlobalScope ? self::GLOBAL_DIMENSION_HASH : $scope->hash;
    }

    /**
     * @return list<string>
     */
    private static function dimensionHashes(MetaDataDimensionSpacePoints|MetaDataGlobalScope $scope): array
    {
        if ($scope instanceof MetaDataGlobalScope) {
            return [self::GLOBAL_DIMENSION_HASH];
        }
        return $scope->map(static fn (MetaDataDimensionSpacePoint $dimensionSpacePoint) => $dimensionSpacePoint->hash);
    }
}
