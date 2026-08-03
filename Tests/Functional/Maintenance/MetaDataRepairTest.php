<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Functional\Maintenance;

use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Maintenance\MetaDataRepair;
use Neos\MetaData\Maintenance\MetaDataRepairAction;
use Neos\MetaData\Maintenance\MetaDataRepairActionType;
use Neos\MetaData\MetaDataManager;
use Neos\MetaData\Tests\Functional\AbstractMetaDataTestCase;
use Neos\MetaData\Tests\Functional\Fixtures\DimensionsFixture;
use Neos\MetaData\Tests\Functional\Fixtures\PropertyDefinitionsFixture;

class MetaDataRepairTest extends AbstractMetaDataTestCase
{
    private MetaDataManager $metaDataManager;
    private MetaDataRepair $metaDataRepair;
    private MetaDataAssetReference $asset;
    private MetaDataDimensionSpacePoint $de;
    private MetaDataDimensionSpacePoint $en;
    private MetaDataDimensionSpacePoint $fr;

    public function setUp(): void
    {
        parent::setUp();
        $dimensions = DimensionsFixture::languages();
        $this->metaDataManager = new MetaDataManager($dimensions, PropertyDefinitionsFixture::default(), $this->storage);
        $this->metaDataRepair = new MetaDataRepair($this->metaDataManager, $dimensions, $this->storage);
        $this->asset = MetaDataAssetReference::create('neos', 'some-asset');
        $this->de = DimensionsFixture::language('de');
        $this->en = DimensionsFixture::language('en');
        $this->fr = DimensionsFixture::language('fr');
    }

    /**
     * @test
     */
    public function consistentDataNeedsNoRepair(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme');

        self::assertSame([], $this->metaDataRepair->analyze());
    }

    /**
     * @test
     */
    public function localizedValuesOfAGlobalPropertyAreConsolidatedIntoTheDefaultChainWinner(): void
    {
        $this->addRawValue($this->asset, 'copyright', $this->de->hash, '© Acme');
        $this->addRawValue($this->asset, 'copyright', $this->en->hash, '© Acme Inc');

        $actions = $this->metaDataRepair->analyze();
        $promotions = self::actionsOfType($actions, MetaDataRepairActionType::promoteToGlobalScope);
        self::assertCount(1, $promotions);
        self::assertSame('© Acme Inc', $promotions[0]->storedValue->value, 'the value of the default dimension wins');
        self::assertCount(2, self::actionsOfType($actions, MetaDataRepairActionType::deleteWrongScope));

        $this->metaDataRepair->apply($actions);
        self::assertSame('© Acme Inc', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright')->value);
        self::assertCount(1, $this->storedValues());
    }

    /**
     * @test
     */
    public function theOnlyLocalizedValueOfAGlobalPropertyIsKeptEvenIfItIsNotOnTheDefaultChain(): void
    {
        $this->addRawValue($this->asset, 'copyright', $this->fr->hash, '© Foto Meier');

        $this->metaDataRepair->apply($this->metaDataRepair->analyze());

        self::assertSame('© Foto Meier', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright')->value);
        self::assertCount(1, $this->storedValues());
    }

    /**
     * @test
     */
    public function anExistingSharedValueWinsOverStaleLocalizedOnes(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Current');
        $this->addRawValue($this->asset, 'copyright', $this->en->hash, '© Stale');

        $actions = $this->metaDataRepair->analyze();
        self::assertSame([], self::actionsOfType($actions, MetaDataRepairActionType::promoteToGlobalScope), 'live data must not be overwritten');

        $this->metaDataRepair->apply($actions);
        self::assertSame('© Current', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright')->value);
        self::assertCount(1, $this->storedValues());
    }

    /**
     * @test
     */
    public function aSharedValueOfALocalizedPropertyIsPromotedToTheDefaultDimension(): void
    {
        $this->addRawValue($this->asset, 'caption', 'global', 'A cat');

        $actions = $this->metaDataRepair->analyze();
        self::assertCount(1, self::actionsOfType($actions, MetaDataRepairActionType::promoteToDefaultDimension));

        $this->metaDataRepair->apply($actions);
        self::assertSame('A cat', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->en)->ownValue);
        self::assertSame('A cat', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de)->inheritedValue);
        self::assertCount(1, $this->storedValues());
    }

    /**
     * @test
     */
    public function aSharedValueIsNotPromotedIfTheDefaultDimensionAlreadyHasAValue(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $this->addRawValue($this->asset, 'caption', 'global', 'Stale');

        $actions = $this->metaDataRepair->analyze();
        self::assertSame([], self::actionsOfType($actions, MetaDataRepairActionType::promoteToDefaultDimension));

        $this->metaDataRepair->apply($actions);
        self::assertSame('A cat', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->en)->value);
        self::assertCount(1, $this->storedValues());
    }

    /**
     * @test
     */
    public function valuesOfUnconfiguredDimensionsAreOnlyRemovedWhenPruning(): void
    {
        $this->addRawValue($this->asset, 'caption', DimensionsFixture::language('es')->hash, 'Un gato');

        $actions = $this->metaDataRepair->analyze();
        self::assertCount(1, self::actionsOfType($actions, MetaDataRepairActionType::deleteObsoleteDimension));

        self::assertSame(0, $this->metaDataRepair->apply($actions));
        self::assertCount(1, $this->storedValues());

        self::assertSame(1, $this->metaDataRepair->apply($actions, prune: true));
        self::assertSame([], $this->storedValues());
    }

    /**
     * @test
     */
    public function valuesOfUndefinedPropertiesAreOnlyRemovedWhenPruning(): void
    {
        $this->addRawValue($this->asset, 'formerProperty', $this->en->hash, 'obsolete');

        $actions = $this->metaDataRepair->analyze();
        self::assertCount(1, self::actionsOfType($actions, MetaDataRepairActionType::deleteUndefinedProperty));

        self::assertSame(0, $this->metaDataRepair->apply($actions));
        self::assertSame(1, $this->metaDataRepair->apply($actions, prune: true));
        self::assertSame([], $this->storedValues());
    }

    /**
     * @test
     */
    public function valuesOfOtherAssetsAreNotAffected(): void
    {
        $otherAsset = MetaDataAssetReference::create('neos', 'other-asset');
        $this->addRawValue($this->asset, 'copyright', $this->en->hash, '© Acme');
        $this->metaDataManager->setMetaDataPropertyValue($otherAsset, 'caption', 'A cat', $this->en);

        $this->metaDataRepair->apply($this->metaDataRepair->analyze());

        self::assertSame('A cat', $this->metaDataManager->getMetaDataPropertyValue($otherAsset, 'caption', $this->en)->value);
    }

    /**
     * @test
     */
    public function pruningIsRefusedWithoutConfiguredDimensions(): void
    {
        $dimensions = DimensionsFixture::none();
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

    // -----------------------

    /**
     * @param list<MetaDataRepairAction> $actions
     * @return list<MetaDataRepairAction>
     */
    private static function actionsOfType(array $actions, MetaDataRepairActionType $type): array
    {
        return array_values(array_filter($actions, static fn (MetaDataRepairAction $action) => $action->type === $type));
    }
}
