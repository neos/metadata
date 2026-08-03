<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Functional\Storage;

use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;
use Neos\MetaData\Storage\MetaDataStoredValue;
use Neos\MetaData\Tests\Functional\AbstractMetaDataTestCase;

/**
 * Verifies the parts of the storage that only exist in SQL: the upsert, the lookup by scope, the
 * removal of values and the search.
 */
class MetaDataStorageProviderDbalAdapterTest extends AbstractMetaDataTestCase
{
    private MetaDataAssetReference $asset;
    private MetaDataPropertyName $caption;
    private MetaDataDimensionSpacePoint $de;
    private MetaDataDimensionSpacePoint $en;

    public function setUp(): void
    {
        parent::setUp();
        $this->asset = MetaDataAssetReference::create('neos', 'some-asset');
        $this->caption = MetaDataPropertyName::fromString('caption');
        $this->de = MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de']);
        $this->en = MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'en']);
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
}
