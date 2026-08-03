<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Functional;

use InvalidArgumentException;
use Neos\MetaData\Domain\Dto\MetaDataAssetFilter;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;
use Neos\MetaData\MetaDataManager;
use Neos\MetaData\Tests\Functional\Fixtures\DimensionsFixture;
use Neos\MetaData\Tests\Functional\Fixtures\PropertyDefinitionsFixture;

class MetaDataManagerTest extends AbstractMetaDataTestCase
{
    private MetaDataManager $metaDataManager;
    private MetaDataAssetReference $asset;
    private MetaDataDimensionSpacePoint $de;
    private MetaDataDimensionSpacePoint $en;
    private MetaDataDimensionSpacePoint $fr;

    public function setUp(): void
    {
        parent::setUp();
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

        self::assertCount(1, $this->storedValues());
        self::assertSame('© Acme Inc', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright', $this->en)->value);
    }

    /**
     * @test
     */
    public function unsettingAGlobalValueIgnoresTheDimensionSpacePoint(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', $this->en);
        $this->metaDataManager->unsetMetaDataPropertyValue($this->asset, 'copyright', $this->de);

        self::assertSame([], $this->storedValues());
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
        $metaDataManager = new MetaDataManager(DimensionsFixture::none(), PropertyDefinitionsFixture::default(), $this->storage);

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

    /**
     * @test
     */
    public function assetsAreFoundByASearchTermInAnyProperty(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $other = MetaDataAssetReference::create('neos', 'other-asset');
        $this->metaDataManager->setMetaDataPropertyValue($other, 'copyright', '© Cat Photos', $this->en);

        self::assertSame(
            ['neos:other-asset', 'neos:some-asset'],
            $this->find(MetaDataAssetFilter::create(searchTerm: 'cat')),
            'the global scope property matches as well',
        );
    }

    /**
     * @test
     */
    public function theSearchTermMatchesAnywhereInAValueAndIgnoresCase(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A CATalogue picture', $this->en);

        self::assertSame(['neos:some-asset'], $this->find(MetaDataAssetFilter::create(searchTerm: 'cat')));
    }

    /**
     * @test
     */
    public function inheritedValuesAreFound(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);

        self::assertSame(
            ['neos:some-asset'],
            $this->find(MetaDataAssetFilter::create(dimensionSpacePoint: $this->de, searchTerm: 'cat')),
            'German inherits the English caption, so it is what an editor working in German sees',
        );
    }

    /**
     * @test
     */
    public function shadowedValuesAreNotFound(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Eine Katze', $this->de);

        self::assertSame(
            [],
            $this->find(MetaDataAssetFilter::create(dimensionSpacePoint: $this->de, searchTerm: 'cat')),
            'the German value overrides the English one, so "cat" is not what German resolves to',
        );
        self::assertSame(['neos:some-asset'], $this->find(MetaDataAssetFilter::create(dimensionSpacePoint: $this->en, searchTerm: 'cat')));
    }

    /**
     * @test
     */
    public function valuesOfUnrelatedDimensionsAreNotFound(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Un chat', $this->fr);

        self::assertSame([], $this->find(MetaDataAssetFilter::create(dimensionSpacePoint: $this->de, searchTerm: 'chat')));
    }

    /**
     * @test
     */
    public function globalValuesAreFoundRegardlessOfTheDimensionSpacePoint(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme');

        foreach ([$this->de, $this->en, $this->fr, null] as $dimensionSpacePoint) {
            self::assertSame(
                ['neos:some-asset'],
                $this->find(MetaDataAssetFilter::create(dimensionSpacePoint: $dimensionSpacePoint, searchTerm: 'acme')),
            );
        }
    }

    /**
     * @test
     */
    public function anOmittedDimensionSpacePointRefersToTheDefaultOne(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Eine Katze', $this->de);

        self::assertSame([], $this->find(MetaDataAssetFilter::create(searchTerm: 'Katze')), 'the default dimension is English');
        self::assertSame(['neos:some-asset'], $this->find(MetaDataAssetFilter::create(dimensionSpacePoint: $this->de, searchTerm: 'Katze')));
    }

    /**
     * @test
     */
    public function anAssetMatchingSeveralTimesIsReturnedOnce(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Cat Photos');

        self::assertSame(['neos:some-asset'], $this->find(MetaDataAssetFilter::create(searchTerm: 'cat')));
    }

    /**
     * @test
     */
    public function theSearchCanBeRestrictedToProperties(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $other = MetaDataAssetReference::create('neos', 'other-asset');
        $this->metaDataManager->setMetaDataPropertyValue($other, 'copyright', '© Cat Photos');

        self::assertSame(
            ['neos:some-asset'],
            $this->find(MetaDataAssetFilter::create(searchTerm: 'cat', propertyNames: MetaDataPropertyNames::create('caption'))),
        );
        self::assertSame(
            ['neos:other-asset'],
            $this->find(MetaDataAssetFilter::create(searchTerm: 'cat', propertyNames: MetaDataPropertyNames::create('copyright'))),
        );
    }

    /**
     * @test
     */
    public function theSearchCanBeRestrictedToAnAssetSource(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $sameAssetInAnotherSource = MetaDataAssetReference::create('other-source', 'some-asset');
        $this->metaDataManager->setMetaDataPropertyValue($sameAssetInAnotherSource, 'caption', 'A cat', $this->en);

        self::assertSame(['other-source:some-asset'], $this->find(MetaDataAssetFilter::create(assetSourceId: 'other-source', searchTerm: 'cat')));
    }

    /**
     * @test
     */
    public function anOmittedSearchTermMatchesEveryAssetWithAValue(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $other = MetaDataAssetReference::create('neos', 'other-asset');
        $this->metaDataManager->setMetaDataPropertyValue($other, 'copyright', '© Acme');

        self::assertSame(['neos:other-asset', 'neos:some-asset'], $this->find(MetaDataAssetFilter::create()));
        self::assertSame(
            ['neos:some-asset'],
            $this->find(MetaDataAssetFilter::create(propertyNames: MetaDataPropertyNames::create('caption'))),
            'which assets have a caption at all',
        );
    }

    /**
     * @test
     */
    public function anEmptySearchTermIsTreatedLikeAnOmittedOne(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);

        self::assertSame(['neos:some-asset'], $this->find(MetaDataAssetFilter::create(searchTerm: '   ')));
    }

    /**
     * @test
     */
    public function theSearchTermIsTrimmed(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);

        self::assertSame(['neos:some-asset'], $this->find(MetaDataAssetFilter::create(searchTerm: '  cat  ')));
    }

    /**
     * @test
     */
    public function likeWildcardsInTheSearchTermAreEscaped(): void
    {
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat', $this->en);
        $discounted = MetaDataAssetReference::create('neos', 'discounted');
        $this->metaDataManager->setMetaDataPropertyValue($discounted, 'caption', 'Reduced by 50%', $this->en);

        self::assertSame(['neos:discounted'], $this->find(MetaDataAssetFilter::create(searchTerm: '50%')));
        self::assertSame([], $this->find(MetaDataAssetFilter::create(searchTerm: 'c_t')));
        self::assertSame([], $this->find(MetaDataAssetFilter::create(searchTerm: '\\')));
    }

    /**
     * @test
     */
    public function valuesOfAScopeThatContradictsTheConfigurationAreNotFound(): void
    {
        $this->addRawValue($this->asset, 'copyright', $this->en->hash, '© Stale');
        $this->addRawValue($this->asset, 'caption', 'global', 'Stale caption');

        self::assertSame([], $this->find(MetaDataAssetFilter::create(searchTerm: 'stale')));
    }

    /**
     * @test
     */
    public function valuesOfUnconfiguredDimensionsAreNotFound(): void
    {
        $this->addRawValue($this->asset, 'caption', DimensionsFixture::language('es')->hash, 'Un gato');

        self::assertSame([], $this->find(MetaDataAssetFilter::create(searchTerm: 'gato')));
    }

    /**
     * @test
     */
    public function valuesOfUndefinedPropertiesAreNotFound(): void
    {
        $this->addRawValue($this->asset, 'formerProperty', $this->en->hash, 'A cat');

        self::assertSame([], $this->find(MetaDataAssetFilter::create(searchTerm: 'cat')));
    }

    /**
     * @test
     */
    public function searchingForAnUndefinedPropertyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776278047);
        $this->find(MetaDataAssetFilter::create(propertyNames: MetaDataPropertyNames::create('unknown')));
    }

    /**
     * @test
     */
    public function searchingInAnUnconfiguredDimensionSpacePointThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776279083);
        $this->find(MetaDataAssetFilter::create(dimensionSpacePoint: DimensionsFixture::language('es')));
    }

    // -----------------------

    /**
     * @return list<string> the matched asset references as "<assetSourceId>:<assetId>"
     */
    private function find(MetaDataAssetFilter $filter): array
    {
        $matches = [];
        foreach ($this->metaDataManager->findAssets($filter) as $assetReference) {
            $matches[] = $assetReference->assetSourceId . ':' . $assetReference->assetId;
        }
        return $matches;
    }
}
