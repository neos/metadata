<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Maintenance;

use Neos\Flow\Tests\UnitTestCase;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Maintenance\MetaDataRepair;
use Neos\MetaData\Maintenance\MetaDataRepairAction;
use Neos\MetaData\Maintenance\MetaDataRepairActionType;
use Neos\MetaData\MetaDataManager;
use Neos\MetaData\Storage\MetaDataStorage;
use Neos\MetaData\Storage\MetaDataStoredValue;
use Neos\MetaData\Tests\Unit\Fixtures\DimensionSpacePointProviderMocks;
use Neos\MetaData\Tests\Unit\Fixtures\MaintainableMetaDataStorage;
use Neos\MetaData\Tests\Unit\Fixtures\PropertyDefinitionsFixture;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

/**
 * Tests which stored values {@see MetaDataRepair} considers wrong and what it does about them.
 *
 * That is decided from the stored values and the configuration alone, so the storage is a test double:
 * every test states the rows it starts from and asserts the actions that come out, plus the calls that
 * applying them makes. Whether those calls actually land in the database is covered by the tests of the
 * storage implementations.
 */
class MetaDataRepairTest extends UnitTestCase
{
    use DimensionSpacePointProviderMocks;

    private MaintainableMetaDataStorage&MockObject $storage;
    private MetaDataManager $metaDataManager;
    private MetaDataRepair $metaDataRepair;
    private MetaDataAssetReference $asset;
    private MetaDataDimensionSpacePoint $de;
    private MetaDataDimensionSpacePoint $en;
    private MetaDataDimensionSpacePoint $fr;

    /**
     * Every write and deletion, in the order they happened
     *
     * @var list<string>
     */
    private array $calls = [];

    public function setUp(): void
    {
        $this->de = self::language('de');
        $this->en = self::language('en');
        $this->fr = self::language('fr');
        $this->asset = MetaDataAssetReference::create('neos', 'some-asset');

        $dimensions = $this->createLanguageDimensions();
        $this->storage = $this->createMock(MaintainableMetaDataStorage::class);
        $this->storage->method('setMetaDataPropertyValue')->willReturnCallback(
            function (MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, string|int|bool $value, MetaDataDimensionSpacePoint|MetaDataGlobalScope $scope): void {
                $this->calls[] = sprintf(
                    'set %s of %s to "%s" in %s',
                    $propertyName->value,
                    $assetReference->assetId,
                    $value,
                    $scope instanceof MetaDataGlobalScope ? 'global' : $scope->coordinates['language'],
                );
            }
        );
        $this->storage->method('deleteStoredValues')->willReturnCallback(
            function (MetaDataStoredValue ...$storedValues): int {
                foreach ($storedValues as $storedValue) {
                    $this->calls[] = sprintf('delete %s of %s', $storedValue->propertyName->value, $storedValue->assetReference->assetId);
                }
                return count($storedValues);
            }
        );

        $this->metaDataManager = new MetaDataManager($dimensions, PropertyDefinitionsFixture::default(), $this->storage);
        $this->metaDataRepair = new MetaDataRepair($this->metaDataManager, $dimensions, $this->storage);
    }

    /**
     * @test
     */
    public function consistentDataNeedsNoRepair(): void
    {
        $this->storageContains(
            self::localizedValue($this->asset, 'caption', $this->en, 'A cat'),
            self::globalValue($this->asset, 'copyright', '© Acme'),
        );

        self::assertSame([], $this->metaDataRepair->analyze());
    }

    /**
     * @test
     */
    public function localizedValuesOfAGlobalPropertyAreConsolidatedIntoTheDefaultChainWinner(): void
    {
        $this->storageContains(
            self::localizedValue($this->asset, 'copyright', $this->de, '© Acme'),
            self::localizedValue($this->asset, 'copyright', $this->en, '© Acme Inc'),
        );

        $actions = $this->metaDataRepair->analyze();
        $promotions = self::actionsOfType($actions, MetaDataRepairActionType::promoteToGlobalScope);
        self::assertCount(1, $promotions);
        self::assertSame('© Acme Inc', $promotions[0]->storedValue->value, 'the value of the default dimension wins');
        self::assertCount(2, self::actionsOfType($actions, MetaDataRepairActionType::deleteWrongScope));

        self::assertSame(2, $this->metaDataRepair->apply($actions));
        self::assertSame(
            [
                'set copyright of some-asset to "© Acme Inc" in global',
                'delete copyright of some-asset',
                'delete copyright of some-asset',
            ],
            $this->calls,
            'the value is promoted before the rows it came from are deleted',
        );
    }

    /**
     * @test
     */
    public function theOnlyLocalizedValueOfAGlobalPropertyIsKeptEvenIfItIsNotOnTheDefaultChain(): void
    {
        $this->storageContains(self::localizedValue($this->asset, 'copyright', $this->fr, '© Foto Meier'));

        $this->metaDataRepair->apply($this->metaDataRepair->analyze());

        self::assertContains('set copyright of some-asset to "© Foto Meier" in global', $this->calls);
    }

    /**
     * @test
     */
    public function anExistingSharedValueWinsOverStaleLocalizedOnes(): void
    {
        $this->storageContains(
            self::globalValue($this->asset, 'copyright', '© Current'),
            self::localizedValue($this->asset, 'copyright', $this->en, '© Stale'),
        );

        $actions = $this->metaDataRepair->analyze();
        self::assertSame([], self::actionsOfType($actions, MetaDataRepairActionType::promoteToGlobalScope), 'live data must not be overwritten');

        self::assertSame(1, $this->metaDataRepair->apply($actions));
        self::assertSame(['delete copyright of some-asset'], $this->calls, 'only the stale row is removed');
    }

    /**
     * @test
     */
    public function aSharedValueOfALocalizedPropertyIsPromotedToTheDefaultDimension(): void
    {
        $this->storageContains(self::globalValue($this->asset, 'caption', 'A cat'));

        $actions = $this->metaDataRepair->analyze();
        self::assertCount(1, self::actionsOfType($actions, MetaDataRepairActionType::promoteToDefaultDimension));

        $this->metaDataRepair->apply($actions);
        self::assertSame(
            ['set caption of some-asset to "A cat" in en', 'delete caption of some-asset'],
            $this->calls,
        );
    }

    /**
     * @test
     */
    public function aSharedValueIsNotPromotedIfTheDefaultDimensionAlreadyHasAValue(): void
    {
        $this->storageContains(
            self::localizedValue($this->asset, 'caption', $this->en, 'A cat'),
            self::globalValue($this->asset, 'caption', 'Stale'),
        );

        $actions = $this->metaDataRepair->analyze();
        self::assertSame([], self::actionsOfType($actions, MetaDataRepairActionType::promoteToDefaultDimension));

        $this->metaDataRepair->apply($actions);
        self::assertSame(['delete caption of some-asset'], $this->calls);
    }

    /**
     * @test
     */
    public function valuesOfUnconfiguredDimensionsAreOnlyRemovedWhenPruning(): void
    {
        $this->storageContains(self::localizedValue($this->asset, 'caption', self::language('es'), 'Un gato'));

        $actions = $this->metaDataRepair->analyze();
        self::assertCount(1, self::actionsOfType($actions, MetaDataRepairActionType::deleteObsoleteDimension));

        self::assertSame(0, $this->metaDataRepair->apply($actions));
        self::assertSame([], $this->calls, 'nothing is touched without pruning');

        self::assertSame(1, $this->metaDataRepair->apply($actions, prune: true));
        self::assertSame(['delete caption of some-asset'], $this->calls);
    }

    /**
     * @test
     */
    public function valuesOfUndefinedPropertiesAreOnlyRemovedWhenPruning(): void
    {
        $this->storageContains(self::localizedValue($this->asset, 'formerProperty', $this->en, 'obsolete'));

        $actions = $this->metaDataRepair->analyze();
        self::assertCount(1, self::actionsOfType($actions, MetaDataRepairActionType::deleteUndefinedProperty));

        self::assertSame(0, $this->metaDataRepair->apply($actions));
        self::assertSame(1, $this->metaDataRepair->apply($actions, prune: true));
        self::assertSame(['delete formerProperty of some-asset'], $this->calls);
    }

    /**
     * @test
     */
    public function valuesOfOtherAssetsAreNotAffected(): void
    {
        $otherAsset = MetaDataAssetReference::create('neos', 'other-asset');
        $this->storageContains(
            self::localizedValue($this->asset, 'copyright', $this->en, '© Acme'),
            self::localizedValue($otherAsset, 'caption', $this->en, 'A cat'),
        );

        $this->metaDataRepair->apply($this->metaDataRepair->analyze());

        foreach ($this->calls as $call) {
            self::assertStringNotContainsString('other-asset', $call);
        }
    }

    /**
     * Values of the same property but of different assets must not be consolidated into one another
     *
     * @test
     */
    public function eachAssetIsRepairedOnItsOwn(): void
    {
        $otherAsset = MetaDataAssetReference::create('neos', 'other-asset');
        $this->storageContains(
            self::localizedValue($this->asset, 'copyright', $this->en, '© Acme'),
            self::localizedValue($otherAsset, 'copyright', $this->en, '© Other'),
        );

        $promotions = self::actionsOfType($this->metaDataRepair->analyze(), MetaDataRepairActionType::promoteToGlobalScope);

        self::assertCount(2, $promotions);
        self::assertSame(
            ['© Acme', '© Other'],
            array_map(static fn (MetaDataRepairAction $action) => $action->storedValue->value, $promotions),
        );
    }

    /**
     * @test
     */
    public function pruningIsRefusedWithoutConfiguredDimensions(): void
    {
        $dimensions = $this->createEmptyDimensions();
        $metaDataRepair = new MetaDataRepair(
            new MetaDataManager($dimensions, PropertyDefinitionsFixture::default(), $this->storage),
            $dimensions,
            $this->storage,
        );

        self::assertFalse($metaDataRepair->hasConfiguredDimensions());
        self::assertTrue($this->metaDataRepair->hasConfiguredDimensions());
    }

    /**
     * @test
     */
    public function repairingIsSupportedByStoragesImplementingTheMaintenanceInterface(): void
    {
        self::assertTrue($this->metaDataRepair->isSupported());
    }

    /**
     * @test
     */
    public function repairingIsUnsupportedByStoragesNotImplementingTheMaintenanceInterface(): void
    {
        self::assertFalse($this->repairWithPlainStorage()->isSupported());
    }

    /**
     * @test
     */
    public function applyingWithAStorageThatCannotBeMaintainedThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1776280001);
        $this->repairWithPlainStorage()->apply([]);
    }

    // -----------------------

    private function repairWithPlainStorage(): MetaDataRepair
    {
        $dimensions = $this->createLanguageDimensions();
        $storage = $this->createMock(MetaDataStorage::class);
        return new MetaDataRepair(
            new MetaDataManager($dimensions, PropertyDefinitionsFixture::default(), $storage),
            $dimensions,
            $storage,
        );
    }

    private function storageContains(MetaDataStoredValue ...$storedValues): void
    {
        $this->storage->method('findAllStoredValues')->willReturn($storedValues);
    }

    private static function localizedValue(MetaDataAssetReference $assetReference, string $propertyName, MetaDataDimensionSpacePoint $dimensionSpacePoint, string $value): MetaDataStoredValue
    {
        return new MetaDataStoredValue($assetReference, MetaDataPropertyName::fromString($propertyName), $dimensionSpacePoint->hash, false, $value);
    }

    private static function globalValue(MetaDataAssetReference $assetReference, string $propertyName, string $value): MetaDataStoredValue
    {
        return new MetaDataStoredValue($assetReference, MetaDataPropertyName::fromString($propertyName), 'global', true, $value);
    }

    /**
     * @param list<MetaDataRepairAction> $actions
     * @return list<MetaDataRepairAction>
     */
    private static function actionsOfType(array $actions, MetaDataRepairActionType $type): array
    {
        return array_values(array_filter($actions, static fn (MetaDataRepairAction $action) => $action->type === $type));
    }
}
