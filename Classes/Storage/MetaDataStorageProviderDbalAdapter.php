<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Doctrine\DBAL\Connection;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;

final readonly class MetaDataStorageProviderDbalAdapter implements MetaDataStorage
{

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function setMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string|int|bool $propertyValue, MetaDataDimensionSpacePoint $dimensionSpacePoint): void
    {
        $statement = <<<MYSQL
            INSERT INTO neos_metadata_value
                (asset_source_id, asset_id, property_name, property_value, dimension_hash)
            VALUES
                (:assetSourceId, :assetId, :propertyName, :propertyValue, :dimensionHash)
            ON DUPLICATE KEY UPDATE property_value = :propertyValue
        MYSQL;
        $this->connection->executeStatement(
            $statement,
            [
                'assetSourceId' => $assetReference->assetSourceId,
                'assetId' => $assetReference->assetId,
                'propertyName' => $propertyName->value,
                'propertyValue' => $propertyValue,
                'dimensionHash' => $dimensionSpacePoint->hash,
            ]
        );
    }

    public function unsetMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): void
    {
        $this->connection->delete('neos_metadata_value', [
            'asset_source_id' => $assetReference->assetSourceId,
            'asset_id' => $assetReference->assetId,
            'property_name' => $propertyName->value,
            'dimension_hash' => $dimensionSpacePoint->hash,
        ]);
    }

    public function getMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): string|int|bool|null
    {
        return $this->connection->fetchOne('SELECT property_value FROM neos_metadata_value WHERE asset_source_id = :assetSourceId AND asset_id = :assetId AND property_name = :propertyName AND dimension_hash IN (... :dimensionHash', [
            'assetSourceId' => $assetReference->assetSourceId,
            'assetId' => $assetReference->assetId,
            'propertyName' => $propertyName->value,
            'dimensionHash' => $dimensionSpacePoint->hash,
        ]);
    }
}
