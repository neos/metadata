<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
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

    public function getMetaDataPropertyValue(MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoints $dimensionSpacePoints): string|int|bool|null
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('property_value')
            ->from('neos_metadata_value')
            ->where(
                $query->expr()->and(
                    $query->expr()->eq('asset_source_id', ':assetSourceId'),
                    $query->expr()->eq('asset_id', ':assetId'),
                    $query->expr()->eq('property_name', ':propertyName'),
                    $query->expr()->in('dimension_hash', ':dimensionHashes'),
                )
            )
            ->setParameters([
                'assetSourceId' => $assetReference->assetSourceId,
                'assetId' => $assetReference->assetId,
                'propertyName' => $propertyName->value,
                'dimensionHashes' => iterator_to_array($dimensionSpacePoints->getHashIterator()),
            ], [
                'dimensionHashes' => ArrayParameterType::STRING,
            ]);
        return $query->fetchOne();
    }
}
