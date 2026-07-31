<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\DimensionSpacePointProvider;

use Neos\ContentRepository\Domain\Service\ConfigurationContentDimensionPresetSource;
use Neos\Flow\Tests\UnitTestCase;
use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProviderContentRepositoryAdapter;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;

/**
 * The fallback chain determines which value wins when reading metadata, so its order is part of the
 * behaviour rather than an implementation detail.
 */
class DimensionSpacePointProviderContentRepositoryAdapterTest extends UnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        // Loading the class here rather than from within a test keeps compile time deprecations of the
        // (optional) content repository package out of the test output
        class_exists(ConfigurationContentDimensionPresetSource::class);
    }

    public function setUp(): void
    {
        if (!class_exists(ConfigurationContentDimensionPresetSource::class)) {
            self::markTestSkipped('neos/content-repository is not installed');
        }
    }

    /**
     * @test
     */
    public function theDefaultDimensionSpacePointIsBuiltFromTheDefaultOfEveryDimension(): void
    {
        $adapter = $this->adapter([
            'language' => ['default' => 'en', 'defaultPreset' => 'en', 'presets' => ['en' => ['values' => ['en']], 'de' => ['values' => ['de', 'en']]]],
            'country' => ['default' => 'us', 'defaultPreset' => 'us', 'presets' => ['us' => ['values' => ['us']], 'at' => ['values' => ['at', 'us']]]],
        ]);

        self::assertSame(['language' => 'en', 'country' => 'us'], $adapter->getDefaultDimensionSpacePoint()->coordinates);
    }

    /**
     * @test
     */
    public function theChainStartsWithTheDimensionSpacePointItself(): void
    {
        $adapter = $this->adapter(['language' => ['default' => 'en', 'defaultPreset' => 'en', 'presets' => ['en' => ['values' => ['en']], 'de' => ['values' => ['de', 'en']]]]]);

        $chain = $adapter->getDimensionSpacePointChain(MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de']));

        self::assertSame([['language' => 'de'], ['language' => 'en']], self::coordinates($chain));
    }

    /**
     * @test
     */
    public function aDimensionSpacePointWithoutFallbacksIsItsOwnChain(): void
    {
        $adapter = $this->adapter(['language' => ['default' => 'en', 'defaultPreset' => 'en', 'presets' => ['en' => ['values' => ['en']], 'de' => ['values' => ['de', 'en']]]]]);

        $chain = $adapter->getDimensionSpacePointChain(MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'en']));

        self::assertSame([['language' => 'en']], self::coordinates($chain));
    }

    /**
     * @test
     */
    public function chainsOfSeveralDimensionsAreOrderedByTotalFallbackDistance(): void
    {
        $adapter = $this->adapter([
            'language' => ['default' => 'en', 'defaultPreset' => 'en', 'presets' => ['en' => ['values' => ['en']], 'de' => ['values' => ['de', 'en']]]],
            'country' => ['default' => 'us', 'defaultPreset' => 'us', 'presets' => ['us' => ['values' => ['us']], 'at' => ['values' => ['at', 'us']]]],
        ]);

        $chain = $adapter->getDimensionSpacePointChain(MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de', 'country' => 'at']));

        self::assertSame([
            ['language' => 'de', 'country' => 'at'],
            ['language' => 'de', 'country' => 'us'],
            ['language' => 'en', 'country' => 'at'],
            ['language' => 'en', 'country' => 'us'],
        ], self::coordinates($chain), 'the most specific combination must come first, the most generic last');
    }

    /**
     * @test
     */
    public function withoutContentDimensionsTheOnlyDimensionSpacePointIsTheEmptyOne(): void
    {
        $adapter = $this->adapter([]);

        self::assertSame([], $adapter->getDefaultDimensionSpacePoint()->coordinates);
        self::assertTrue($adapter->isDimensionSpacePointValid(MetaDataDimensionSpacePoint::fromCoordinates([])));
        self::assertFalse($adapter->isDimensionSpacePointValid(MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de'])));
    }

    /**
     * @test
     */
    public function unknownDimensionValuesAreNotValid(): void
    {
        $adapter = $this->adapter(['language' => ['default' => 'en', 'defaultPreset' => 'en', 'presets' => ['en' => ['values' => ['en']], 'de' => ['values' => ['de', 'en']]]]]);

        self::assertTrue($adapter->isDimensionSpacePointValid(MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de'])));
        self::assertFalse($adapter->isDimensionSpacePointValid(MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'es'])));
        self::assertFalse($adapter->isDimensionSpacePointValid(MetaDataDimensionSpacePoint::fromCoordinates([])), 'a dimension must not be omitted');
    }

    /**
     * A preset identifier does not have to equal the primary value of that preset. Everything else in
     * the adapter works with values, so enumerating by identifier would produce dimension space points
     * that cannot be validated or resolved.
     *
     * @test
     */
    public function dimensionSpacePointsAreEnumeratedByPresetValueRatherThanByPresetIdentifier(): void
    {
        $adapter = $this->adapter([
            'language' => [
                'default' => 'en',
                'defaultPreset' => 'english',
                'presets' => ['english' => ['values' => ['en']], 'german' => ['values' => ['de', 'en']]],
            ],
        ]);

        self::assertSame([['language' => 'en'], ['language' => 'de']], self::coordinates($adapter->getDimensionSpacePoints()));
    }

    /**
     * @test
     */
    public function everyEnumeratedDimensionSpacePointIsValid(): void
    {
        $adapter = $this->adapter([
            'language' => [
                'default' => 'en',
                'defaultPreset' => 'english',
                'presets' => ['english' => ['values' => ['en']], 'german' => ['values' => ['de', 'en']]],
            ],
        ]);

        foreach ($adapter->getDimensionSpacePoints() as $dimensionSpacePoint) {
            self::assertTrue(
                $adapter->isDimensionSpacePointValid($dimensionSpacePoint),
                sprintf('%s was enumerated but is not considered valid', $dimensionSpacePoint),
            );
        }
        self::assertTrue(
            $adapter->getDimensionSpacePoints()->include($adapter->getDefaultDimensionSpacePoint()),
            'the default dimension space point must be among the enumerated ones',
        );
    }

    /**
     * @test
     */
    public function presetsWithoutValuesAreNotEnumerated(): void
    {
        $adapter = $this->adapter([
            'language' => ['default' => 'en', 'defaultPreset' => 'en', 'presets' => ['en' => ['values' => ['en']], 'broken' => []]],
        ]);

        self::assertSame([['language' => 'en']], self::coordinates($adapter->getDimensionSpacePoints()));
    }

    // -----------------------

    /**
     * @param array<string, mixed> $presets
     */
    private function adapter(array $presets): DimensionSpacePointProviderContentRepositoryAdapter
    {
        $presetSource = new ConfigurationContentDimensionPresetSource();
        $presetSource->setConfiguration($presets);
        return new DimensionSpacePointProviderContentRepositoryAdapter($presetSource);
    }

    /**
     * @return list<array<string, string>>
     */
    private static function coordinates(iterable $dimensionSpacePoints): array
    {
        $coordinates = [];
        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            $coordinates[] = $dimensionSpacePoint->coordinates;
        }
        return $coordinates;
    }
}
