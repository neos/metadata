<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use Neos\Flow\Tests\UnitTestCase;
use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Domain\Dto\MetaDataAssetFilter;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataGlobalScope;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;
use Neos\MetaData\MetaDataManager;
use Neos\MetaData\Storage\MetaDataStorage;
use Neos\MetaData\Tests\Unit\Fixtures\DimensionSpacePointProviderMocks;
use Neos\MetaData\Tests\Unit\Fixtures\PropertyDefinitionsFixture;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the resolution rules that live in the {@see MetaDataManager}: which dimension space points are
 * candidates for a value, which of the stored values wins, where an inherited value stems from and how
 * values are coerced to the type a property is defined for.
 *
 * The storage and the dimension space point provider are test doubles, so that every test states the
 * stored values it resolves from. What the storage does with a lookup is its own business and is
 * covered by the tests of its implementations.
 */
class MetaDataManagerTest extends UnitTestCase
{
    use DimensionSpacePointProviderMocks;

    private MetaDataStorage&MockObject $storage;
    private DimensionSpacePointProvider&MockObject $dimensionSpacePointProvider;
    private MetaDataManager $metaDataManager;
    private MetaDataAssetReference $asset;
    private MetaDataDimensionSpacePoint $de;
    private MetaDataDimensionSpacePoint $en;
    private MetaDataDimensionSpacePoint $fr;
    private MetaDataDimensionSpacePoint $es;

    public function setUp(): void
    {
        $this->de = self::language('de');
        $this->en = self::language('en');
        $this->fr = self::language('fr');
        $this->es = self::language('es');

        $this->storage = $this->createMock(MetaDataStorage::class);
        $this->dimensionSpacePointProvider = $this->createLanguageDimensions();
        $this->metaDataManager = $this->managerFor(PropertyDefinitionsFixture::default());
        $this->asset = MetaDataAssetReference::create('neos', 'some-asset');
    }

    // ----------------------- reading

    /**
     * @test
     */
    public function localizedValueWithoutFallbackIsItsOwnValue(): void
    {
        $this->storageContains(['caption' => [$this->de->hash => 'Eine Katze']]);

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
        $this->storageContains(['caption' => [$this->en->hash => 'A cat']]);

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
        $this->storageContains(['caption' => [$this->en->hash => 'A cat', $this->de->hash => 'Eine Katze']]);

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
    public function onlyTheClosestFallbackIsInherited(): void
    {
        $this->storageContains(['caption' => [$this->en->hash => 'A cat', $this->fr->hash => 'Un chat']]);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertSame('A cat', $value->inheritedValue);
        self::assertTrue($value->inheritedFrom?->equals($this->en), 'French is not on the German fallback chain');
    }

    /**
     * The candidates are looked up in one go, so the manager must not rely on the storage to return
     * them in the order of the chain
     *
     * @test
     */
    public function resolutionDoesNotDependOnTheOrderTheStorageReturnsValuesIn(): void
    {
        $this->storageContains(['caption' => [$this->de->hash => 'Eine Katze', $this->en->hash => 'A cat']]);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
        self::assertSame('Eine Katze', $value->ownValue);
        self::assertSame('A cat', $value->inheritedValue);
    }

    /**
     * @test
     */
    public function valuesOfUnrelatedDimensionsAreNotInherited(): void
    {
        $this->storageContains(['caption' => [$this->fr->hash => 'Un chat']]);

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
        $this->storageContains([]);

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
        $this->storageContains(['caption' => [$this->en->hash => 'A cat']]);

        self::assertSame('A cat', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption')->ownValue);
    }

    /**
     * @test
     */
    public function localizedValuesAreLookedUpAlongTheWholeChain(): void
    {
        $this->storage->expects(self::once())
            ->method('getMetaDataPropertyValues')
            ->with(
                $this->asset,
                self::callback(static fn (MetaDataPropertyName $name) => $name->equals('caption')),
                self::callback(fn (MetaDataDimensionSpacePoints $scope) => self::hashesOf($scope) === [$this->de->hash, $this->en->hash]),
            )
            ->willReturn([]);

        $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'caption', $this->de);
    }

    /**
     * @test
     */
    public function allDefinedPropertiesArePresentInTheResult(): void
    {
        $this->storageContains([]);

        self::assertSame(
            ['copyright' => null, 'caption' => null],
            $this->metaDataManager->getMetaDataPropertyValues($this->asset, $this->de)->toArray(),
        );
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

    // ----------------------- global scope

    /**
     * @test
     */
    public function globalValuesAreLookedUpInTheGlobalScope(): void
    {
        $this->storage->expects(self::once())
            ->method('getMetaDataPropertyValues')
            ->with($this->asset, self::anything(), self::isInstanceOf(MetaDataGlobalScope::class))
            ->willReturn(['global' => '© Acme']);

        self::assertSame('© Acme', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright', $this->de)->value);
    }

    /**
     * @test
     */
    public function globalValueIsSharedByAllDimensions(): void
    {
        $this->storageContains(['copyright' => ['global' => '© Acme']]);

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
        $this->storageContains(['copyright' => ['global' => '© Acme']]);

        $value = $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright', $this->de);
        self::assertSame('© Acme', $value->ownValue, 'a shared value is always an own value');
        self::assertNull($value->inheritedValue, 'a shared value has nothing to inherit from');
        self::assertNull($value->inheritedFrom);
        self::assertFalse($value->isInherited());
    }

    /**
     * @test
     */
    public function readingAGlobalValueAcceptsAnyDimensionSpacePoint(): void
    {
        $this->storageContains(['copyright' => ['global' => '© Acme']]);

        self::assertSame('© Acme', $this->metaDataManager->getMetaDataPropertyValue($this->asset, 'copyright', $this->es)->value);
    }

    // ----------------------- writing

    /**
     * @test
     */
    public function settingALocalizedValueWritesItToTheGivenDimension(): void
    {
        $this->storage->expects(self::once())
            ->method('setMetaDataPropertyValue')
            ->with(
                $this->asset,
                self::callback(static fn (MetaDataPropertyName $name) => $name->equals('caption')),
                'Eine Katze',
                self::callback(fn (MetaDataDimensionSpacePoint $scope) => $scope->equals($this->de)),
            );

        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Eine Katze', $this->de);
    }

    /**
     * @test
     */
    public function settingAValueWithoutADimensionSpacePointWritesItToTheDefaultOne(): void
    {
        $this->storage->expects(self::once())
            ->method('setMetaDataPropertyValue')
            ->with(self::anything(), self::anything(), self::anything(), self::callback(fn (MetaDataDimensionSpacePoint $scope) => $scope->equals($this->en)));

        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'A cat');
    }

    /**
     * @test
     */
    public function settingAGlobalValueWritesItToTheGlobalScope(): void
    {
        $this->storage->expects(self::once())
            ->method('setMetaDataPropertyValue')
            ->with(self::anything(), self::anything(), '© Acme', self::isInstanceOf(MetaDataGlobalScope::class));

        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', $this->de);
    }

    /**
     * The dimension space point is ignored for global properties, so it is not validated either
     *
     * @test
     */
    public function writingAGlobalValueAcceptsAnyDimensionSpacePoint(): void
    {
        $this->storage->expects(self::once())
            ->method('setMetaDataPropertyValue')
            ->with(self::anything(), self::anything(), self::anything(), self::isInstanceOf(MetaDataGlobalScope::class));

        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'copyright', '© Acme', $this->es);
    }

    /**
     * @test
     */
    public function unsettingALocalizedValueOnlyAffectsTheGivenDimension(): void
    {
        $this->storage->expects(self::once())
            ->method('unsetMetaDataPropertyValue')
            ->with($this->asset, self::anything(), self::callback(fn (MetaDataDimensionSpacePoint $scope) => $scope->equals($this->de)));

        $this->metaDataManager->unsetMetaDataPropertyValue($this->asset, 'caption', $this->de);
    }

    /**
     * @test
     */
    public function unsettingAGlobalValueIgnoresTheDimensionSpacePoint(): void
    {
        $this->storage->expects(self::once())
            ->method('unsetMetaDataPropertyValue')
            ->with(self::anything(), self::anything(), self::isInstanceOf(MetaDataGlobalScope::class));

        $this->metaDataManager->unsetMetaDataPropertyValue($this->asset, 'copyright', $this->de);
    }

    /**
     * @test
     */
    public function writingAnUndefinedPropertyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776278047);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'unknown', 'whatever', $this->de);
    }

    /**
     * @test
     */
    public function writingAnUnconfiguredDimensionSpacePointThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776279083);
        $this->metaDataManager->setMetaDataPropertyValue($this->asset, 'caption', 'Hola', $this->es);
    }

    // ----------------------- property types

    /**
     * @test
     */
    public function valuesAreCoercedToTheDefinedTypeBeforeTheyAreStored(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $written = [];
        $this->storage->method('setMetaDataPropertyValue')
            ->willReturnCallback(static function (MetaDataAssetReference $ref, MetaDataPropertyName $name, string|int|bool $value) use (&$written): void {
                $written[$name->value] = $value;
            });

        $manager->setMetaDataPropertyValue($this->asset, 'width', '42', $this->de);
        $manager->setMetaDataPropertyValue($this->asset, 'featured', 'yes', $this->de);

        self::assertSame(['width' => '42', 'featured' => '1'], $written, 'string input from the command line or a form is coerced');
    }

    /**
     * @test
     */
    public function writingAValueThatDoesNotMatchTheTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1785715201);
        $this->managerFor(PropertyDefinitionsFixture::typed())->setMetaDataPropertyValue($this->asset, 'width', 'abc', $this->de);
    }

    /**
     * @test
     */
    public function storedValuesAreReadBackAsTheDefinedType(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $this->storageContains([
            'width' => [$this->de->hash => '42'],
            'featured' => [$this->de->hash => '1'],
        ]);

        self::assertSame(42, $manager->getMetaDataPropertyValue($this->asset, 'width', $this->de)->value);
        self::assertTrue($manager->getMetaDataPropertyValue($this->asset, 'featured', $this->de)->value);
    }

    /**
     * @test
     */
    public function falseAndZeroAreDistinguishableFromAnAbsentValue(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $this->storageContains([
            'width' => [$this->de->hash => '0'],
            'featured' => [$this->de->hash => '0'],
        ]);

        $width = $manager->getMetaDataPropertyValue($this->asset, 'width', $this->de);
        self::assertSame(0, $width->value);
        self::assertTrue($width->hasOwnValue());

        $featured = $manager->getMetaDataPropertyValue($this->asset, 'featured', $this->de);
        self::assertFalse($featured->value);
        self::assertTrue($featured->hasOwnValue(), 'FALSE is a value, not the absence of one');
    }

    /**
     * @test
     */
    public function storedValuesThatDoNotMatchTheTypeAreTreatedLikeAbsentOnes(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $this->storageContains(['width' => [$this->de->hash => 'abc']]);

        $value = $manager->getMetaDataPropertyValue($this->asset, 'width', $this->de);
        self::assertNull($value->value);
        self::assertFalse($value->hasOwnValue());
    }

    /**
     * @test
     */
    public function anUnreadableValueDoesNotShadowAReadableFallback(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $this->storageContains(['width' => [$this->de->hash => 'abc', $this->en->hash => '42']]);

        $value = $manager->getMetaDataPropertyValue($this->asset, 'width', $this->de);
        self::assertSame(42, $value->value, 'the English value is still readable');
        self::assertNull($value->ownValue);
        self::assertTrue($value->isInherited());
    }

    /**
     * @test
     */
    public function globalValuesAreCoercedAsWell(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::create(['copyright' => true, 'caption' => false]));
        $this->storageContains(['copyright' => ['global' => '42']]);

        self::assertSame('42', $manager->getMetaDataPropertyValue($this->asset, 'copyright')->value);
    }

    /**
     * @test
     */
    public function floatValuesAreCoercedAndReadBack(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $written = [];
        $this->storage->method('setMetaDataPropertyValue')
            ->willReturnCallback(static function (MetaDataAssetReference $ref, MetaDataPropertyName $name, string $value) use (&$written): void {
                $written[$name->value] = $value;
            });

        $manager->setMetaDataPropertyValue($this->asset, 'rating', 4.2, $this->de);
        self::assertSame(['rating' => '4.2'], $written);

        $this->storageContains(['rating' => [$this->de->hash => '4.2']]);
        self::assertSame(4.2, $manager->getMetaDataPropertyValue($this->asset, 'rating', $this->de)->value);
    }

    /**
     * @test
     */
    public function arrayValuesAreCoercedAndReadBack(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $written = [];
        $this->storage->method('setMetaDataPropertyValue')
            ->willReturnCallback(static function (MetaDataAssetReference $ref, MetaDataPropertyName $name, string $value) use (&$written): void {
                $written[$name->value] = $value;
            });

        $manager->setMetaDataPropertyValue($this->asset, 'tags', ['cat', 'cute'], $this->de);
        self::assertSame(['tags' => '["cat","cute"]'], $written);

        $this->storageContains(['tags' => [$this->de->hash => '["cat","cute"]']]);
        self::assertSame(['cat', 'cute'], $manager->getMetaDataPropertyValue($this->asset, 'tags', $this->de)->value);
    }

    /**
     * @test
     */
    public function dateTimeValuesAreCoercedAndReadBack(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $written = [];
        $this->storage->method('setMetaDataPropertyValue')
            ->willReturnCallback(static function (MetaDataAssetReference $ref, MetaDataPropertyName $name, string $value) use (&$written): void {
                $written[$name->value] = $value;
            });

        $manager->setMetaDataPropertyValue($this->asset, 'publishedAt', new DateTimeImmutable('2024-01-02T10:00:00+00:00'), $this->de);
        self::assertSame(['publishedAt' => '2024-01-02T10:00:00+00:00'], $written);

        $this->storageContains(['publishedAt' => [$this->de->hash => '2024-01-02T10:00:00+00:00']]);
        self::assertEquals(new DateTimeImmutable('2024-01-02T10:00:00+00:00'), $manager->getMetaDataPropertyValue($this->asset, 'publishedAt', $this->de)->value);
    }

    /**
     * @test
     */
    public function anEmptyArrayIsDistinguishableFromAnAbsentValue(): void
    {
        $manager = $this->managerFor(PropertyDefinitionsFixture::typed());
        $this->storageContains(['tags' => [$this->de->hash => '[]']]);

        $value = $manager->getMetaDataPropertyValue($this->asset, 'tags', $this->de);
        self::assertSame([], $value->value);
        self::assertTrue($value->hasOwnValue(), 'an empty array is a value, not the absence of one');
    }

    // ----------------------- finding assets

    /**
     * @test
     */
    public function findingAssetsSplitsThePropertiesByScopeAndPassesTheOrderedChain(): void
    {
        $this->storage->expects(self::once())
            ->method('findAssets')
            ->with(
                'neos',
                'cat',
                self::callback(static fn (MetaDataPropertyNames $names) => self::valuesOf($names) === ['caption']),
                self::callback(fn (MetaDataDimensionSpacePoints $chain) => self::hashesOf($chain) === [$this->de->hash, $this->en->hash]),
                self::callback(static fn (MetaDataPropertyNames $names) => self::valuesOf($names) === ['copyright']),
            )
            ->willReturn([]);

        iterator_to_array($this->metaDataManager->findAssets(MetaDataAssetFilter::create(
            assetSourceId: 'neos',
            dimensionSpacePoint: $this->de,
            searchTerm: 'cat',
        )), false);
    }

    /**
     * @test
     */
    public function findingAssetsWithoutAPropertyFilterSearchesAllDefinedProperties(): void
    {
        $this->storage->expects(self::once())
            ->method('findAssets')
            ->with(
                null,
                null,
                self::callback(static fn (MetaDataPropertyNames $names) => self::valuesOf($names) === ['caption']),
                self::anything(),
                self::callback(static fn (MetaDataPropertyNames $names) => self::valuesOf($names) === ['copyright']),
            )
            ->willReturn([]);

        iterator_to_array($this->metaDataManager->findAssets(MetaDataAssetFilter::create()), false);
    }

    /**
     * @test
     */
    public function findingAssetsCanBeRestrictedToProperties(): void
    {
        $this->storage->expects(self::once())
            ->method('findAssets')
            ->with(
                self::anything(),
                self::anything(),
                self::callback(static fn (MetaDataPropertyNames $names) => self::valuesOf($names) === ['caption']),
                self::anything(),
                self::callback(static fn (MetaDataPropertyNames $names) => self::valuesOf($names) === []),
            )
            ->willReturn([]);

        iterator_to_array($this->metaDataManager->findAssets(MetaDataAssetFilter::create(
            propertyNames: MetaDataPropertyNames::create('caption'),
        )), false);
    }

    /**
     * @test
     */
    public function findingAssetsWithoutADimensionSpacePointUsesTheDefaultOne(): void
    {
        $this->storage->expects(self::once())
            ->method('findAssets')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::callback(fn (MetaDataDimensionSpacePoints $chain) => self::hashesOf($chain) === [$this->en->hash]),
                self::anything(),
            )
            ->willReturn([]);

        iterator_to_array($this->metaDataManager->findAssets(MetaDataAssetFilter::create()), false);
    }

    /**
     * @test
     */
    public function findingAssetsReturnsWhatTheStorageFound(): void
    {
        $match = MetaDataAssetReference::create('neos', 'some-asset');
        $this->storage->method('findAssets')->willReturn([$match]);

        self::assertSame([$match], iterator_to_array($this->metaDataManager->findAssets(MetaDataAssetFilter::create()), false));
    }

    /**
     * @test
     */
    public function findingAssetsForAnUndefinedPropertyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776278047);
        $this->metaDataManager->findAssets(MetaDataAssetFilter::create(propertyNames: MetaDataPropertyNames::create('unknown')));
    }

    /**
     * @test
     */
    public function findingAssetsInAnUnconfiguredDimensionSpacePointThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1776279083);
        $this->metaDataManager->findAssets(MetaDataAssetFilter::create(dimensionSpacePoint: $this->es));
    }

    // ----------------------- dimension configuration

    /**
     * @test
     */
    public function theDimensionSpacePointConfigurationIsPassedThrough(): void
    {
        self::assertSame(
            [$this->en->hash, $this->de->hash, $this->fr->hash],
            self::hashesOf($this->metaDataManager->getDimensionSpacePointConfiguration()),
        );
    }

    /**
     * @test
     */
    public function resultShapeDoesNotDependOnTheDimensionConfiguration(): void
    {
        $this->dimensionSpacePointProvider = $this->createEmptyDimensions();
        $manager = $this->managerFor(PropertyDefinitionsFixture::default());
        $this->storageContains([]);

        self::assertSame(['copyright' => null, 'caption' => null], $manager->getMetaDataPropertyValues($this->asset)->toArray());
    }

    /**
     * @test
     */
    public function thePropertyDefinitionsArePassedThrough(): void
    {
        $definitions = PropertyDefinitionsFixture::typed();

        self::assertSame($definitions, $this->managerFor($definitions)->getPropertyDefinitions());
    }

    // -----------------------

    private function managerFor(MetaDataPropertyDefinitions $propertyDefinitions): MetaDataManager
    {
        return new MetaDataManager($this->dimensionSpacePointProvider, $propertyDefinitions, $this->storage);
    }

    /**
     * Declares the values the storage holds, by property name and dimension hash. Global values are
     * keyed by the literal "global", as the storage does.
     *
     * @param array<string, array<string, string|int|bool>> $valuesByPropertyName
     */
    private function storageContains(array $valuesByPropertyName): void
    {
        $this->storage->method('getMetaDataPropertyValues')->willReturnCallback(
            static function (MetaDataAssetReference $assetReference, MetaDataPropertyName $propertyName, MetaDataDimensionSpacePoints|MetaDataGlobalScope $scope) use ($valuesByPropertyName): array {
                $values = $valuesByPropertyName[$propertyName->value] ?? [];
                $requestedHashes = $scope instanceof MetaDataGlobalScope
                    ? ['global']
                    : self::hashesOf($scope);
                return array_intersect_key($values, array_flip($requestedHashes));
            }
        );
    }

    /**
     * @return list<string>
     */
    private static function hashesOf(MetaDataDimensionSpacePoints $dimensionSpacePoints): array
    {
        return $dimensionSpacePoints->map(static fn (MetaDataDimensionSpacePoint $dimensionSpacePoint) => $dimensionSpacePoint->hash);
    }

    /**
     * @return list<string>
     */
    private static function valuesOf(MetaDataPropertyNames $propertyNames): array
    {
        return $propertyNames->map(static fn (MetaDataPropertyName $propertyName) => $propertyName->value);
    }
}
