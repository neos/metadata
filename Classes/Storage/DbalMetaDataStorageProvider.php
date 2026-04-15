<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Doctrine\DBAL\Connection;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValue;

final readonly class DbalMetaDataStorageProvider implements MetaDataStorage
{

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function setMetaDataPropertyValue(string $assetId, MetaDataPropertyName $propertyName, MetaDataPropertyValue $propertyValue, MetaDataDimensionSpacePoint $dimensionSpacePoint): void
    {
        $statement = <<<MYSQL
            INSERT INTO neos_metadata_value
                (asset_id, property_name, property_value, dimension_hash)
            VALUES
                (:assetId, :propertyName, :propertyValue, :dimensionHash)
            ON DUPLICATE KEY UPDATE property_value = :propertyValue
        MYSQL;
        $this->connection->executeStatement(
            $statement,
            [
                'assetId' => $assetId,
                'propertyName' => $propertyName->value,
                'propertyValue' => $propertyValue->value,
                'dimensionHash' => $dimensionSpacePoint->hash,
            ]
        );
    }

    public function unsetMetaDataPropertyValue(string $assetId, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): void
    {
        $this->connection->delete('neos_metadata_value', [
            'asset_id' => $assetId,
            'property_name' => $propertyName->value,
            'dimension_hash' => $dimensionSpacePoint->hash,
        ]);
    }

    public function getMetaDataPropertyValue(string $assetId, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint): MetaDataPropertyValue|null
    {
        $value = $this->connection->fetchOne('SELECT property_value FROM neos_metadata_value WHERE asset_id = :assetId AND property_name = :propertyName AND dimension_hash = :dimensionHash', [
            'assetId' => $assetId,
            'propertyName' => $propertyName->value,
            'dimensionHash' => $dimensionSpacePoint->hash,
        ]);
        return $value === false ? null : MetaDataPropertyValue::parse($value);
    }
}
