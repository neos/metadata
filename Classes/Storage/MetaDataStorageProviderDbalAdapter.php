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
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;

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

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string $propertyValue, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void
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

    public function findAssets(
        ?string $assetSourceId,
        ?string $searchTerm,
        MetaDataPropertyNames $localizedPropertyNames,
        MetaDataDimensionSpacePoints $dimensionSpacePointChain,
        MetaDataPropertyNames $globalScopePropertyNames,
    ): iterable {
        $parameters = [];
        $scopeConditions = [];

        $chainHashes = $dimensionSpacePointChain->map(static fn (MetaDataDimensionSpacePoint $dimensionSpacePoint) => $dimensionSpacePoint->hash);
        if (!$localizedPropertyNames->isEmpty() && $chainHashes !== []) {
            $chainPlaceholders = self::bindList($parameters, 'dsp', $chainHashes);
            $namePlaceholders = self::bindList($parameters, 'localizedProperty', $localizedPropertyNames->map(static fn (MetaDataPropertyName $propertyName) => $propertyName->value));
            // The value must be the closest one along the chain – a value further down is shadowed and
            // never surfaces for the dimension space point that was asked for.
            // NOTE: the identity columns are nullable, so the correlation uses the NULL safe `<=>`
            $scopeConditions[] = sprintf(<<<'MYSQL'
                (
                    v.property_name IN (%1$s) AND v.dimension_hash IN (%2$s) AND NOT EXISTS (
                        SELECT 1 FROM %3$s v2
                        WHERE v2.asset_source_id <=> v.asset_source_id
                          AND v2.asset_id <=> v.asset_id
                          AND v2.property_name = v.property_name
                          AND v2.dimension_hash IN (%2$s)
                          AND FIELD(v2.dimension_hash, %2$s) < FIELD(v.dimension_hash, %2$s)
                    )
                )
            MYSQL, $namePlaceholders, $chainPlaceholders, self::TABLE_NAME);
        }

        if (!$globalScopePropertyNames->isEmpty()) {
            $namePlaceholders = self::bindList($parameters, 'globalProperty', $globalScopePropertyNames->map(static fn (MetaDataPropertyName $propertyName) => $propertyName->value));
            $parameters['globalDimensionHash'] = self::GLOBAL_DIMENSION_HASH;
            $scopeConditions[] = sprintf('(v.property_name IN (%s) AND v.dimension_hash = :globalDimensionHash)', $namePlaceholders);
        }

        if ($scopeConditions === []) {
            return [];
        }

        $conditions = [sprintf('(%s)', implode(' OR ', $scopeConditions))];
        if ($assetSourceId !== null) {
            $conditions[] = 'v.asset_source_id = :assetSourceId';
            $parameters['assetSourceId'] = $assetSourceId;
        }
        if ($searchTerm !== null) {
            $conditions[] = "v.property_value LIKE :searchTerm ESCAPE '\\\\'";
            $parameters['searchTerm'] = '%' . self::escapeLikeWildcards($searchTerm) . '%';
        }

        $statement = sprintf(<<<'MYSQL'
            SELECT DISTINCT v.asset_source_id, v.asset_id
            FROM %s v
            WHERE %s
            ORDER BY v.asset_source_id, v.asset_id
        MYSQL, self::TABLE_NAME, implode(' AND ', $conditions));

        return $this->streamAssetReferences($statement, $parameters);
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

    /**
     * Binds the given values as individually named parameters and returns the corresponding placeholder
     * list for an `IN (...)` or `FIELD(...)` expression.
     *
     * The placeholders are named rather than expanded from an array parameter, because the dimension
     * hashes occur multiple times within the same statement.
     *
     * @param array<string, string> $parameters mutated in place
     * @param list<string> $values
     */
    private static function bindList(array &$parameters, string $prefix, array $values): string
    {
        $placeholders = [];
        foreach ($values as $index => $value) {
            $parameterName = $prefix . $index;
            $parameters[$parameterName] = $value;
            $placeholders[] = ':' . $parameterName;
        }
        return implode(', ', $placeholders);
    }

    /**
     * Escapes the characters that are wildcards within a LIKE pattern, so that a search for "50%" does
     * not match every value
     */
    private static function escapeLikeWildcards(string $searchTerm): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm);
    }

    /**
     * @param array<string, string> $parameters
     * @return iterable<MetaDataAssetReference>
     */
    private function streamAssetReferences(string $statement, array $parameters): iterable
    {
        foreach ($this->connection->executeQuery($statement, $parameters)->iterateAssociative() as $row) {
            yield MetaDataAssetReference::create($row['asset_source_id'], $row['asset_id']);
        }
    }

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
