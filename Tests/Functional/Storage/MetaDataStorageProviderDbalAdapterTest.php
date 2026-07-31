<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Functional\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Storage\MetaDataStorageProviderDbalAdapter;
use Neos\MetaData\Storage\MetaDataStoredValue;

/**
 * Verifies the parts of the storage that only exist in SQL: the upsert, the lookup by scope and the
 * removal of values.
 *
 * The table is created here rather than by the Doctrine migration, because the values are not mapped as
 * an entity and the functional test schema is derived from entity metadata only. The foreign key of the
 * migration is omitted on purpose – it implements cascading deletion, which is not what is tested here.
 */
class MetaDataStorageProviderDbalAdapterTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    private Connection $connection;
    private MetaDataStorageProviderDbalAdapter $storage;
    private MetaDataAssetReference $asset;
    private MetaDataPropertyName $caption;
    private MetaDataDimensionSpacePoint $de;
    private MetaDataDimensionSpacePoint $en;

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
        $this->asset = MetaDataAssetReference::create('neos', 'some-asset');
        $this->caption = MetaDataPropertyName::fromString('caption');
        $this->de = MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de']);
        $this->en = MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'en']);
    }

    public function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM neos_metadata_value');
        parent::tearDown();
    }

    /**
     * @test
     */
    public function valuesAreStoredAndLookedUpByDimensionHash(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Eine Katze', $this->de);

        self::assertSame(
            [$this->de->hash => 'Eine Katze'],
            $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->de)),
        );
    }

    /**
     * @test
     */
    public function storingAValueTwiceReplacesItInsteadOfDuplicatingIt(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Eine Katze', $this->de);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Ein Kater', $this->de);

        self::assertSame([$this->de->hash => 'Ein Kater'], $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->de)));
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM neos_metadata_value'));
    }

    /**
     * @test
     */
    public function allMatchingValuesAreReturnedForSeveralDimensionSpacePoints(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Eine Katze', $this->de);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);

        $values = $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->de, $this->en));
        self::assertCount(2, $values);
        self::assertSame('Eine Katze', $values[$this->de->hash]);
        self::assertSame('A cat', $values[$this->en->hash]);
    }

    /**
     * @test
     */
    public function dimensionSpacePointsWithoutAValueAreAbsentFromTheResult(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);

        self::assertSame(
            [$this->en->hash => 'A cat'],
            $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->de, $this->en)),
        );
    }

    /**
     * @test
     */
    public function anEmptyScopeIsNotQueried(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);

        self::assertSame([], $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create()));
    }

    /**
     * @test
     */
    public function globalValuesAreStoredSeparatelyFromLocalizedOnes(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Shared', MetaDataGlobalScope::create());

        self::assertSame(
            [$this->en->hash => 'A cat'],
            $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->en)),
            'a localized lookup must not see the shared value',
        );
        self::assertSame(['global' => 'Shared'], $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataGlobalScope::create()));
    }

    /**
     * @test
     */
    public function unsettingAValueOnlyAffectsTheGivenScope(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Eine Katze', $this->de);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->unsetMetaDataPropertyValue($this->asset, $this->caption, $this->de);

        self::assertSame(
            [$this->en->hash => 'A cat'],
            $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->de, $this->en)),
        );
    }

    /**
     * @test
     */
    public function storedValuesOfAllAssetsCanBeIterated(): void
    {
        $otherAsset = MetaDataAssetReference::create('other-source', 'other-asset');
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($otherAsset, $this->caption, 'Shared', MetaDataGlobalScope::create());

        $storedValues = iterator_to_array($this->storage->findAllStoredValues(), false);
        usort($storedValues, static fn (MetaDataStoredValue $a, MetaDataStoredValue $b) => $a->assetReference->assetId <=> $b->assetReference->assetId);

        self::assertCount(2, $storedValues);
        self::assertSame('other-asset', $storedValues[0]->assetReference->assetId);
        self::assertSame('other-source', $storedValues[0]->assetReference->assetSourceId);
        self::assertTrue($storedValues[0]->global);
        self::assertSame('Shared', $storedValues[0]->value);
        self::assertFalse($storedValues[1]->global);
        self::assertSame($this->en->hash, $storedValues[1]->dimensionHash);
    }

    /**
     * @test
     */
    public function deletingStoredValuesRemovesExactlyThoseRows(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Eine Katze', $this->de);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Shared', MetaDataGlobalScope::create());

        $toDelete = array_values(array_filter(
            iterator_to_array($this->storage->findAllStoredValues(), false),
            static fn (MetaDataStoredValue $storedValue) => $storedValue->global,
        ));
        self::assertSame(1, $this->storage->deleteStoredValues(...$toDelete));

        self::assertSame([], $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataGlobalScope::create()));
        self::assertCount(2, $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->de, $this->en)));
    }
}
