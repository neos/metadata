<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Storage\MetaDataStorageProviderDbalAdapter;
use Neos\MetaData\Storage\MetaDataStoredValue;

/**
 * Base class for tests that exercise the metadata storage.
 *
 * Everything below {@see MetaDataStorage} is tested against the real adapter rather than an in-memory
 * double: the resolution of values is spread across the manager and SQL, so a second implementation
 * would only ever be an approximation of it – `utf8mb4_unicode_ci` folds accents and case in ways that
 * PHP string functions do not.
 *
 * The table is created here rather than by the Doctrine migration, because the values are not mapped as
 * an entity and the functional test schema is derived from entity metadata only. The foreign key of the
 * migration is omitted on purpose – it implements cascading deletion, which none of these tests cover.
 */
abstract class AbstractMetaDataTestCase extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    protected Connection $connection;
    protected MetaDataStorageProviderDbalAdapter $storage;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->objectManager->get(EntityManagerInterface::class)->getConnection();
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('The metadata storage adapter requires MySQL or MariaDB');
        }
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS neos_metadata_value (
            `asset_source_id` VARCHAR(255) DEFAULT NULL,
            `asset_id` VARCHAR(40) DEFAULT NULL,
            `property_name` VARCHAR(40) NOT NULL,
            `property_value` VARCHAR(250) NOT NULL,
            `dimension_hash` VARCHAR(250) NOT NULL,
            UNIQUE INDEX idx_unique (`asset_source_id`, `asset_id`, `property_name`, `dimension_hash`)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->connection->executeStatement('DELETE FROM neos_metadata_value');

        $this->storage = new MetaDataStorageProviderDbalAdapter($this->connection);
    }

    public function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM neos_metadata_value');
        parent::tearDown();
    }

    /**
     * Writes a row for an arbitrary dimension hash, including ones that are not (or no longer)
     * configured and ones that contradict the scope a property is defined for
     */
    final protected function addRawValue(MetaDataAssetReference $assetReference, string $propertyName, string $dimensionHash, string $value): void
    {
        $this->connection->insert('neos_metadata_value', [
            'asset_source_id' => $assetReference->assetSourceId,
            'asset_id' => $assetReference->assetId,
            'property_name' => $propertyName,
            'property_value' => $value,
            'dimension_hash' => $dimensionHash,
        ]);
    }

    /**
     * @return list<MetaDataStoredValue>
     */
    final protected function storedValues(): array
    {
        return iterator_to_array($this->storage->findAllStoredValues(), false);
    }
}
