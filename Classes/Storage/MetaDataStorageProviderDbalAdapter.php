<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Neos\Flow\Annotations as Flow;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;

#[Flow\Scope('singleton')]
class MetaDataStorageProviderDbalAdapter implements MetaDataStorage, MetaDataStorageMaintenance
{
    private const string TABLE_NAME = 'neos_metadata_value';

    /**
     * Dimension hash for values of a global scope. A real dimension hash is an MD5 hex string, so this
     * sentinel can never collide with one.
     */
    private const string GLOBAL_DIMENSION_HASH = 'global';

    /**
     * Whether the connected database is MySQL or MariaDB. The upsert and the effective-value ranking of
     * `findAssets()` are emitted slightly differently for those versus other databases (PostgreSQL,
     * SQLite) because neither `ON DUPLICATE KEY UPDATE`/`FIELD()` nor `INSERT ... ON CONFLICT` are
     * portable. Detecting the platform through the connection keeps this adapter working with both the
     * Doctrine DBAL 2.x and 3.x line.
     */
    private readonly bool $mySql;

    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->mySql = $connection->getDatabasePlatform() instanceof MySqlPlatform;
    }

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string|int|bool $propertyValue, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void
    {
        $statement = $this->mySql
            // MySQL/MariaDB: upsert against the unique index via `ON DUPLICATE KEY UPDATE`
            ? sprintf(<<<'SQL'
                INSERT INTO %s
                    (asset_source_id, asset_id, property_name, property_value, dimension_hash)
                VALUES
                    (:assetSourceId, :assetId, :propertyName, :propertyValue, :dimensionHash)
                ON DUPLICATE KEY UPDATE property_value = :propertyValue
                SQL, self::TABLE_NAME)
            // PostgreSQL/SQLite: upsert against an explicitly named unique index via `ON CONFLICT`.
            // MySQL 8.0+ also knows `ON CONFLICT`, but the column list has to match the unique index
            // exactly, which is fine here because the identity of a metadata value is defined by those
            // four columns alone.
            : sprintf(<<<'SQL'
                INSERT INTO %s
                    (asset_source_id, asset_id, property_name, property_value, dimension_hash)
                VALUES
                    (:assetSourceId, :assetId, :propertyName, :propertyValue, :dimensionHash)
                ON CONFLICT (asset_source_id, asset_id, property_name, dimension_hash)
                DO UPDATE SET property_value = EXCLUDED.property_value
                SQL, self::TABLE_NAME);
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
        // The hashes are bound as individually named parameters instead of one array parameter, because
        // the `ArrayParameterType` API differs between Doctrine DBAL 2.x and 3.x. This way the statement
        // works with either line.
        $parameters = [];
        $hashPlaceholders = self::bindList($parameters, 'dimensionHash', $dimensionHashes);
        $parameters['assetSourceId'] = $assetReference->assetSourceId;
        $parameters['assetId'] = $assetReference->assetId;
        $parameters['propertyName'] = $propertyName->value;

        $query = $this->connection->executeQuery(
            sprintf(<<<'SQL'
                SELECT dimension_hash, property_value
                FROM %s
                WHERE asset_source_id = :assetSourceId
                  AND asset_id = :assetId
                  AND property_name = :propertyName
                  AND dimension_hash IN (%s)
                SQL, self::TABLE_NAME, $hashPlaceholders),
            $parameters,
        );
        // NOTE: No ordering – which of the values wins is a domain decision that is made by the MetaDataManager
        $values = [];
        foreach ($query->iterateAssociative() as $row) {
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
            // NOTE: the identity columns are nullable, so the correlation compares NULL-safe. MySQL and
            // MariaDB know `<=>`, other databases (PostgreSQL, SQLite) know `IS [NOT] DISTINCT FROM`.
            $identityComparison = $this->mySql
                ? '(v2.asset_source_id <=> v.asset_source_id AND v2.asset_id <=> v.asset_id)'
                : '(v2.asset_source_id IS NOT DISTINCT FROM v.asset_source_id)
                      AND (v2.asset_id IS NOT DISTINCT FROM v.asset_id)';
            // Ranking of a dimension hash along the chain: MySQL/MariaDB use the `FIELD()` function, the
            // portable branch spells the same position lookup out as a `CASE` expression.
            $rankingComparison = $this->mySql
                ? sprintf('FIELD(v2.dimension_hash, %1$s) < FIELD(v.dimension_hash, %1$s)', $chainPlaceholders)
                : sprintf(
                    'CASE v2.dimension_hash %1$sEND < CASE v.dimension_hash %1$sEND',
                    self::caseWhenThen($parameters, 'rank', $chainHashes),
                );
            $scopeConditions[] = sprintf(<<<'SQL'
                (
                    v.property_name IN (%1$s) AND v.dimension_hash IN (%2$s) AND NOT EXISTS (
                        SELECT 1 FROM %3$s v2
                        WHERE %4$s
                          AND v2.property_name = v.property_name
                          AND v2.dimension_hash IN (%2$s)
                          AND %5$s
                    )
                )
            SQL, $namePlaceholders, $chainPlaceholders, self::TABLE_NAME, $identityComparison, $rankingComparison);
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
            $conditions[] = $this->mySql
                ? "v.property_value LIKE :searchTerm ESCAPE '\\\\'"
                : "v.property_value LIKE :searchTerm ESCAPE '\\'";
            $parameters['searchTerm'] = '%' . self::escapeLikeWildcards($searchTerm) . '%';
        }

        $statement = sprintf(<<<'SQL'
            SELECT DISTINCT v.asset_source_id, v.asset_id
            FROM %s v
            WHERE %s
            ORDER BY v.asset_source_id, v.asset_id
        SQL, self::TABLE_NAME, implode(' AND ', $conditions));

        return $this->streamAssetReferences($statement, $parameters);
    }

    public function findAllStoredValues(): iterable
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('asset_source_id', 'asset_id', 'property_name', 'property_value', 'dimension_hash')
            ->from(self::TABLE_NAME);
        foreach ($query->executeQuery()->iterateAssociative() as $row) {
            /** @var string $assetSourceId */
            $assetSourceId = $row['asset_source_id'];
            /** @var string $assetId */
            $assetId = $row['asset_id'];
            /** @var string $propertyName */
            $propertyName = $row['property_name'];
            /** @var string $dimensionHash */
            $dimensionHash = $row['dimension_hash'];
            /** @var string $propertyValue */
            $propertyValue = $row['property_value'];
            yield new MetaDataStoredValue(
                MetaDataAssetReference::create($assetSourceId, $assetId),
                MetaDataPropertyName::fromString($propertyName),
                $dimensionHash,
                $dimensionHash === self::GLOBAL_DIMENSION_HASH,
                $propertyValue,
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
     * Builds the `WHEN :param THEN <rank>` series of a `CASE` expression that ranks a value by its
     * position in a list – the portable stand-in for MySQL's `FIELD()` for the fallback-chain
     * comparison in `findAssets()`.
     *
     * The placeholders are reused by the `IN (...)` clause, so they must not be expanded from an array
     * parameter (the `ArrayParameterType` API differs between Doctrine DBAL 2.x and 3.x).
     *
     * @param array<string, string> $parameters mutated in place
     * @param list<string>|array<string> $values ordered from the most to the least specific dimension hash
     */
    private static function caseWhenThen(array &$parameters, string $prefix, array $values): string
    {
        $cases = [];
        foreach ($values as $index => $value) {
            $parameterName = $prefix . $index;
            $parameters[$parameterName] = $value;
            $cases[] = sprintf('WHEN :%s THEN %d ', $parameterName, $index);
        }
        return implode('', $cases);
    }

    /**
     * Binds the given values as individually named parameters and returns the corresponding placeholder
     * list for an `IN (...)` expression.
     *
     * The placeholders are named rather than expanded from an array parameter, because the dimension
     * hashes occur multiple times within the same statement.
     *
     * @param array<string, string> $parameters mutated in place
     * @param list<string>|array<string> $values
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
            /** @var string $assetSourceId */
            $assetSourceId = $row['asset_source_id'];
            /** @var string $assetId */
            $assetId = $row['asset_id'];
            yield MetaDataAssetReference::create($assetSourceId, $assetId);
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
