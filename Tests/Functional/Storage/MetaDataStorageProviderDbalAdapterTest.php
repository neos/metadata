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
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;
use Neos\MetaData\Storage\MetaDataStorageMaintenance;
use Neos\MetaData\Storage\MetaDataStorageProviderDbalAdapter;
use Neos\MetaData\Storage\MetaDataStoredValue;

/**
 * Verifies the parts of the storage that only exist in SQL: the upsert, the lookup by scope, the search
 * and the {@see MetaDataStorageMaintenance} surface that `assetmetadata:repair` is built on.
 *
 * The adapter is deliberately MySQL specific - the upsert, the fallback ranking and the null safe
 * correlation all use MySQL syntax - so these tests need a MySQL or MariaDB test database and are
 * skipped elsewhere.
 *
 * The table is created here rather than by the Doctrine migration, because the values are not mapped as
 * an entity and the functional test schema is derived from entity metadata only. The foreign key of the
 * migration is omitted on purpose - it implements cascading deletion, which none of these tests cover.
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

    // ----------------------- asset isolation

    /**
     * @test
     */
    public function valuesOfOtherAssetsAreNotReturned(): void
    {
        $otherAsset = MetaDataAssetReference::create('neos', 'other-asset');
        $this->storage->setMetaDataPropertyValue($otherAsset, $this->caption, 'A cat', $this->en);

        self::assertSame([], $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->en)));
    }

    /**
     * @test
     */
    public function valuesOfOtherAssetSourcesAreNotReturned(): void
    {
        $sameAssetInAnotherSource = MetaDataAssetReference::create('other-source', 'some-asset');
        $this->storage->setMetaDataPropertyValue($sameAssetInAnotherSource, $this->caption, 'A cat', $this->en);

        self::assertSame([], $this->storage->getMetaDataPropertyValues($this->asset, $this->caption, MetaDataDimensionSpacePoints::create($this->en)));
    }

    /**
     * @test
     */
    public function unsettingAValueOfOneAssetLeavesTheOtherAssetsAlone(): void
    {
        $otherAsset = MetaDataAssetReference::create('neos', 'other-asset');
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($otherAsset, $this->caption, 'Another cat', $this->en);

        $this->storage->unsetMetaDataPropertyValue($this->asset, $this->caption, $this->en);

        self::assertSame(
            [$this->en->hash => 'Another cat'],
            $this->storage->getMetaDataPropertyValues($otherAsset, $this->caption, MetaDataDimensionSpacePoints::create($this->en)),
        );
    }

    // ----------------------- maintenance

    /**
     * @test
     */
    public function deletingAValueThatIsNotStoredChangesNothing(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $absent = new MetaDataStoredValue($this->asset, $this->caption, $this->de->hash, false, 'Eine Katze');

        self::assertSame(0, $this->storage->deleteStoredValues($absent));
        self::assertCount(1, $this->storedValues());
    }

    /**
     * @test
     */
    public function deletingWithoutAnyValuesChangesNothing(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);

        self::assertSame(0, $this->storage->deleteStoredValues());
        self::assertCount(1, $this->storedValues());
    }

    /**
     * The dimension hash of a stored value can be handed straight back to the storage, which is what
     * `assetmetadata:repair` relies on
     *
     * @test
     */
    public function storedValuesCanBeDeletedByWhatWasIterated(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Shared', MetaDataGlobalScope::create());

        self::assertSame(2, $this->storage->deleteStoredValues(...$this->storedValues()));
        self::assertSame([], $this->storedValues());
    }

    /**
     * @test
     */
    public function storedValuesOfUnconfiguredDimensionsAndUndefinedPropertiesAreIterated(): void
    {
        $this->addRawValue($this->asset, 'formerProperty', $this->en->hash, 'obsolete');
        $this->addRawValue($this->asset, 'caption', 'some-obsolete-hash', 'Un gato');

        $storedValues = $this->storedValues();
        usort($storedValues, static fn (MetaDataStoredValue $a, MetaDataStoredValue $b) => $a->propertyName->value <=> $b->propertyName->value);

        self::assertCount(2, $storedValues, 'repairing must be able to see values that reads can never return');
        self::assertSame('caption', $storedValues[0]->propertyName->value);
        self::assertSame('some-obsolete-hash', $storedValues[0]->dimensionHash);
        self::assertFalse($storedValues[0]->global);
        self::assertSame('formerProperty', $storedValues[1]->propertyName->value);
    }

    /**
     * @test
     */
    public function anEmptyTableIteratesToNothing(): void
    {
        self::assertSame([], $this->storedValues());
    }

    // ----------------------- searching

    /**
     * @test
     */
    public function assetsAreFoundBySearchTermInLocalizedAndGlobalProperties(): void
    {
        $other = MetaDataAssetReference::create('neos', 'other-asset');
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($other, MetaDataPropertyName::fromString('copyright'), '© Cat Photos', MetaDataGlobalScope::create());

        self::assertSame(['neos:other-asset', 'neos:some-asset'], $this->find('cat'), 'and ordered by asset source id, then asset id');
    }

    /**
     * @test
     */
    public function theSearchTermMatchesAnywhereInAValueAndIgnoresCase(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A CATalogue picture', $this->en);

        self::assertSame(['neos:some-asset'], $this->find('cat'));
    }

    /**
     * @test
     */
    public function inheritedValuesAreFound(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);

        self::assertSame(['neos:some-asset'], $this->find('cat', chain: [$this->de, $this->en]));
    }

    /**
     * @test
     */
    public function shadowedValuesAreNotFound(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Eine Katze', $this->de);

        self::assertSame([], $this->find('cat', chain: [$this->de, $this->en]), 'the German value overrides the English one');
        self::assertSame(['neos:some-asset'], $this->find('cat', chain: [$this->en]));
    }

    /**
     * @test
     */
    public function valuesOutsideTheChainAreNotFound(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'Un chat', MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'fr']));

        self::assertSame([], $this->find('chat', chain: [$this->de, $this->en]));
    }

    /**
     * @test
     */
    public function globalValuesAreFoundRegardlessOfTheChain(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, MetaDataPropertyName::fromString('copyright'), '© Acme', MetaDataGlobalScope::create());

        self::assertSame(['neos:some-asset'], $this->find('acme', chain: [$this->de, $this->en]));
        self::assertSame(['neos:some-asset'], $this->find('acme', chain: [$this->en]));
    }

    /**
     * @test
     */
    public function anAssetMatchingSeveralTimesIsReturnedOnce(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($this->asset, MetaDataPropertyName::fromString('copyright'), '© Cat Photos', MetaDataGlobalScope::create());

        self::assertSame(['neos:some-asset'], $this->find('cat'));
    }

    /**
     * @test
     */
    public function theSearchCanBeRestrictedToAnAssetSource(): void
    {
        $sameAssetInAnotherSource = MetaDataAssetReference::create('other-source', 'some-asset');
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($sameAssetInAnotherSource, $this->caption, 'A cat', $this->en);

        self::assertSame(['other-source:some-asset'], $this->find('cat', assetSourceId: 'other-source'));
    }

    /**
     * @test
     */
    public function anOmittedSearchTermMatchesEveryAssetWithAValue(): void
    {
        $other = MetaDataAssetReference::create('neos', 'other-asset');
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($other, MetaDataPropertyName::fromString('copyright'), '© Acme', MetaDataGlobalScope::create());

        self::assertSame(['neos:other-asset', 'neos:some-asset'], $this->find(null));
    }

    /**
     * @test
     */
    public function likeWildcardsInTheSearchTermAreEscaped(): void
    {
        $discounted = MetaDataAssetReference::create('neos', 'discounted');
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);
        $this->storage->setMetaDataPropertyValue($discounted, $this->caption, 'Reduced by 50%', $this->en);

        self::assertSame(['neos:discounted'], $this->find('50%'));
        self::assertSame([], $this->find('c_t'));
        self::assertSame([], $this->find('\\'));
    }

    /**
     * @test
     */
    public function valuesOfAScopeThatContradictsTheSearchedOneAreNotFound(): void
    {
        $this->addRawValue($this->asset, 'copyright', $this->en->hash, '© Stale');
        $this->addRawValue($this->asset, 'caption', 'global', 'Stale caption');

        self::assertSame([], $this->find('stale', chain: [$this->en]));
    }

    /**
     * @test
     */
    public function searchingWithoutAnyPropertyNamesReturnsNothing(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);

        self::assertSame([], iterator_to_array($this->storage->findAssets(
            null,
            'cat',
            MetaDataPropertyNames::createEmpty(),
            MetaDataDimensionSpacePoints::create($this->en),
            MetaDataPropertyNames::createEmpty(),
        ), false), 'an empty IN () would be a SQL error, so no query must be issued at all');
    }

    /**
     * @test
     */
    public function searchingWithAnEmptyChainIgnoresLocalizedProperties(): void
    {
        $this->storage->setMetaDataPropertyValue($this->asset, $this->caption, 'A cat', $this->en);

        self::assertSame([], iterator_to_array($this->storage->findAssets(
            null,
            'cat',
            MetaDataPropertyNames::create($this->caption),
            MetaDataDimensionSpacePoints::create(),
            MetaDataPropertyNames::createEmpty(),
        ), false));
    }

    // -----------------------

    /**
     * Searches `caption` as a localized and `copyright` as a global scope property, which is how the
     * manager splits the default configuration.
     *
     * @param list<MetaDataDimensionSpacePoint>|null $chain ordered from the most to the least specific, defaults to English only
     * @return list<string> the matched asset references as "<assetSourceId>:<assetId>"
     */
    private function find(?string $searchTerm, ?array $chain = null, ?string $assetSourceId = null): array
    {
        $matches = [];
        $assetReferences = $this->storage->findAssets(
            $assetSourceId,
            $searchTerm,
            MetaDataPropertyNames::create('caption'),
            MetaDataDimensionSpacePoints::create(...($chain ?? [$this->en])),
            MetaDataPropertyNames::create('copyright'),
        );
        foreach ($assetReferences as $assetReference) {
            $matches[] = $assetReference->assetSourceId . ':' . $assetReference->assetId;
        }
        return $matches;
    }

    private function addRawValue(MetaDataAssetReference $assetReference, string $propertyName, string $dimensionHash, string $value): void
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
    private function storedValues(): array
    {
        return iterator_to_array($this->storage->findAllStoredValues(), false);
    }
}
