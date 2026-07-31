<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit;

use InvalidArgumentException;
use Neos\Flow\Tests\UnitTestCase;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\MetaDataManager;
use Neos\MetaData\Tests\Unit\Fixtures\DimensionsFixture;
use Neos\MetaData\Tests\Unit\Fixtures\InMemoryMetaDataStorage;
use Neos\MetaData\Tests\Unit\Fixtures\PropertyDefinitionsFixture;

class MetaDataManagerTest extends UnitTestCase
{
    private InMemoryMetaDataStorage $storage;
    private MetaDataManager $metaDataManager;
    private MetaDataAssetReference $asset;
    private MetaDataDimensionSpacePoint $de;
    private MetaDataDimensionSpacePoint $en;
    private MetaDataDimensionSpacePoint $fr;

    public function setUp(): void
    {
        $this->storage = new InMemoryMetaDataStorage();
        $this->metaDataManager = new MetaDataManager(
            DimensionsFixture::languages(),
            PropertyDefinitionsFixture::default(),
            $this->storage,
        );
        $this->asset = MetaDataAssetReference::create('neos', 'some-asset');
        $this->de = DimensionsFixture::language('de');
        $this->en = DimensionsFixture::language('en');
        $this->fr = DimensionsFixture::language('fr');
    }

    /**
     * @test
     */
    public function localizedValueWithoutFallbackIsItsOwnValue(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Eine Katze', $this->de);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertSame('Eine Katze', $value->value);
        self::assertSame('Eine Katze', $value->ownValue);
        self::assertNull($value->inheritedValue);
        self::assertNull($value->inheritedFrom);
        self::assertTrue($value->hasOwnValue());
        self::assertFalse($value->isInherited());
    }

    /**
     * @test
     */
    public function localizedValueFallsBackToTheFallbackDimension(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertSame('A cat', $value->value);
        self::assertNull($value->ownValue, 'the editing use case must not see the fallback value');
        self::assertSame('A cat', $value->inheritedValue);
        self::assertTrue($value->inheritedFrom?->equals($this->en));
        self::assertTrue($value->isInherited());
    }

    /**
     * @test
     */
    public function ownAndInheritedValueAreReturnedSideBySide(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Eine Katze', $this->de);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertSame('Eine Katze', $value->value, 'the own value wins');
        self::assertSame('Eine Katze', $value->ownValue);
        self::assertSame('A cat', $value->inheritedValue, 'the translation hint is available even though the value is overridden');
        self::assertTrue($value->inheritedFrom?->equals($this->en));
        self::assertFalse($value->isInherited());
    }

    /**
     * @test
     */
    public function valuesOfUnrelatedDimensionsAreNotInherited(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Un chat', $this->fr);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertNull($value->value);
        self::assertNull($value->ownValue);
        self::assertNull($value->inheritedValue);
    }

    /**
     * @test
     */
    public function missingValuesResolveToEmpty(): void
    {
        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertNull($value->value);
        self::assertNull($value->ownValue);
        self::assertNull($value->inheritedValue);
        self::assertNull($value->inheritedFrom);
        self::assertFalse($value->hasOwnValue());
        self::assertFalse($value->isInherited());
    }

    /**
     * @test
     */
    public function omittedDimensionSpacePointRefersToTheDefaultOne(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat');

        self::assertSame('A cat', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->en)->ownValue);
        self::assertSame('A cat', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption')->ownValue);
    }

    /**
     * @test
     */
    public function globalValueIsSharedByAllDimensions(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', $this->de);

        foreach ([$this->de, $this->en, $this->fr, null] as $dimensionSpacePoint) {
            $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright', $dimensionSpacePoint);
            self::assertSame('© Acme', $value->value);
            self::assertSame('© Acme', $value->ownValue);
        }
    }

    /**
     * @test
     */
    public function globalValueIsNeverInherited(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', $this->en);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright', $this->de);
        self::assertSame('© Acme', $value->ownValue, 'a shared value is always an own value');
        self::assertNull($value->inheritedValue, 'a shared value has nothing to inherit from');
        self::assertNull($value->inheritedFrom);
        self::assertFalse($value->isInherited());
    }

    /**
     * @test
     */
    public function globalValueIsStoredOnlyOnce(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', $this->de);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme Inc', $this->fr);

        self::assertCount(1, $this->storage->all());
        self::assertSame('© Acme Inc', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright', $this->en)->value);
    }

    /**
     * @test
     */
    public function unsettingAGlobalValueIgnoresTheDimensionSpacePoint(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', $this->en);
        $this->metaDataManager->unsetMetaDataPropertyValue($this->asset, 'copyright', $this->de);

        self::assertSame([], $this->storage->all());
    }

    /**
     * @test
     */
    public function unsettingALocalizedValueOnlyAffectsItsDimension(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Eine Katze', $this->de);
        $this->metaDataManager->unsetMetaDataPropertyValue($this->asset, 'caption', $this->de);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertNull($value->ownValue);
        self::assertSame('A cat', $value->inheritedValue);
    }

    /**
     * @test
     */
    public function allDefinedPropertiesArePresentInTheResult(): void
    {
        $values = $this->metaDataManager->getMetaDataPropertyValues($this->asset, $this->de);

        self::assertSame(['copyright' => null, 'caption' => null], $values->toArray());
    }

    /**
     * @test
     */
    public function resultShapeDoesNotDependOnTheDimensionConfiguration(): void
    {
        $metaDataManager = new MetaDataManager(DimensionsFixture::none(), PropertyDefinitionsFixture::default(), new InMemoryMetaDataStorage());

        self::assertSame(['copyright' => null, 'caption' => null], $metaDataManager->getMetaDataPropertyValues($this->asset)->toArray());
    }

    /**
     * @test
     */
    public function readingAnUndefinedPropertyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776278047);
        $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'unknown', $this->de);
    }

    /**
     * @test
     */
    public function writingAnUnconfiguredDimensionSpacePointThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776279083);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Hola', DimensionsFixture::language('es'));
    }

    /**
     * The dimension space point is ignored for global properties, so it is not validated either
     *
     * @test
     */
    public function writingAGlobalValueAcceptsAnyDimensionSpacePoint(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', DimensionsFixture::language('es'));

        self::assertSame('© Acme', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright')->value);
    }

    /**
     * @test
     */
    public function valuesOfOtherAssetsAreNotReturned(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $otherAsset = MetaDataAssetReference::create('neos', 'other-asset');

        self::assertNull($this->metaDataManager->getMetaDataPropertyValue($otherAsset, 'caption', $this->en)->value);
    }

    /**
     * @test
     */
    public function valuesOfOtherAssetSourcesAreNotReturned(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $sameAssetInAnotherSource = MetaDataAssetReference::create('other-source', 'some-asset');

        self::assertNull($this->metaDataManager->getMetaDataPropertyValue($sameAssetInAnotherSource, 'caption', $this->en)->value);
    }
}
