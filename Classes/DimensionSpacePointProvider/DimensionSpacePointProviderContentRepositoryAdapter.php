<?php
declare(strict_types=1);

namespace Neos\MetaData\DimensionSpacePointProvider;

use Neos\ContentRepository\Domain\Service\ConfigurationContentDimensionPresetSource;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;

class DimensionSpacePointProviderContentRepositoryAdapter implements DimensionSpacePointProvider
{
    /**
     * @var array<string, array{
     *     default?: string,
     *     presets?: array<string, array{
     *         values?: list<string>
     *     }>
     * }>|null
     */
    private ?array $allPresets = null;

    public function __construct(
        private readonly ConfigurationContentDimensionPresetSource $configurationContentDimensionPresetSource,
    ) {
    }

    public function getDimensionSpacePoints(): MetaDataDimensionSpacePoints
    {
        $presets = $this->getAllPresets();
        return MetaDataDimensionSpacePoints::create(...array_map(
            fn ($coords) => MetaDataDimensionSpacePoint::fromCoordinates($coords),
            $this->createAllPresetCombinations($presets)
        ));
    }

    /**
     * @return array<string, array{
     *     default?: string,
     *     presets?: array<string, array{
     *         values?: list<string>
     *     }>
     * }>
     */
    private function getAllPresets(): array
    {
        if ($this->allPresets === null) {
            $this->allPresets = $this->configurationContentDimensionPresetSource->getAllPresets();
        }
        return $this->allPresets;
    }

    public function getDefaultDimensionSpacePoint(): MetaDataDimensionSpacePoint
    {
        $presets = $this->getAllPresets();
        $ccordinates = [];
        foreach ($presets as $dimensionName => $dimensionConfig) {
            $ccordinates[$dimensionName] = $dimensionConfig['default'] ?? '';
        }
        return MetaDataDimensionSpacePoint::fromCoordinates($ccordinates);
    }

    public function getDimensionSpacePointChain(?MetaDataDimensionSpacePoint $dimensionSpacePoint = null): MetaDataDimensionSpacePoints
    {
        if ($dimensionSpacePoint === null) {
            $dimensionSpacePoint = $this->getDefaultDimensionSpacePoint();
        }

        if ($dimensionSpacePoint->coordinates === []) {
            return MetaDataDimensionSpacePoints::create($dimensionSpacePoint);
        }

        // For each coordinate, resolve the ordered fallback chain from the matching preset
        $perDimensionChains = [];
        foreach ($dimensionSpacePoint->coordinates as $dimensionName => $primaryValue) {
            $chain = [$primaryValue]; // safe default: just the value itself
            foreach ($this->getAllPresets()[$dimensionName]['presets'] ?? [] as $preset) {
                $values = $preset['values'] ?? null;
                if ($values !== null && $values[0] === $primaryValue) {
                    $chain = $values;
                    break;
                }
            }
            $perDimensionChains[$dimensionName] = $chain;
        }

        // Build Cartesian product of all per-dimension chains, tracking fallback distance per combo
        $combos = [['coords' => [], 'distance' => 0]];
        foreach ($perDimensionChains as $dimensionName => $chain) {
            $expanded = [];
            foreach ($combos as $combo) {
                foreach ($chain as $index => $value) {
                    $expanded[] = [
                        'coords' => array_merge($combo['coords'], [$dimensionName => $value]),
                        'distance' => $combo['distance'] + $index,
                    ];
                }
            }
            $combos = $expanded;
        }

        // Sort by total fallback distance: most specific (0) first
        usort($combos, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        $spacePoints = array_map(
            fn ($combo) => MetaDataDimensionSpacePoint::fromCoordinates($combo['coords']),
            $combos
        );

        return MetaDataDimensionSpacePoints::create(...$spacePoints);
    }

    public function isDimensionSpacePointValid(MetaDataDimensionSpacePoint $dimensionSpacePoint): bool
    {
        if (empty($this->getAllPresets())) {
            return $dimensionSpacePoint->coordinates === [];
        }

        if (count($dimensionSpacePoint->coordinates) !== count($this->getAllPresets())) {
            return false;
        }

        $presetIdentifiers = [];
        foreach ($dimensionSpacePoint->coordinates as $dimensionName => $value) {
            foreach ($this->getAllPresets()[$dimensionName]['presets'] ?? [] as $presetIdentifier => $preset) {
                $values = $preset['values'] ?? null;
                if ($values !== null && $values[0] === $value) {
                    $presetIdentifiers[$dimensionName] = $presetIdentifier;
                    break;
                }
            }
            if (!isset($presetIdentifiers[$dimensionName])) {
                return false;
            }
        }

        return $this->configurationContentDimensionPresetSource->isPresetCombinationAllowedByConstraints($presetIdentifiers);
    }

    /**
     * The cartesian product of all configured dimension presets.
     *
     * A coordinate is the primary value of a preset, not its identifier – the two are usually the same
     * but need not be (a preset "german" can have the values ["de"]). Everything else in this class
     * works with values: the fallback chain matches on `values[0]` and the default dimension space point
     * is built from the `default` of each dimension.
     *
     * Presets without values are skipped, because they could never be matched anyway.
     */
    /**
     * @param array<string, array{
     *     default?: string,
     *     presets?: array<string, array{
     *         values?: list<string>
     *     }>
     * }> $input
     * @return list<array<string, string>>
     */
    function createAllPresetCombinations(array $input): array
    {
        $result = [[]];
        foreach ($input as $dimensionName => $dimensionConfig) {
            $append = [];
            foreach ($dimensionConfig['presets'] ?? [] as $preset) {
                if (!isset($preset['values'][0])) {
                    continue;
                }
                foreach ($result as $coordinates) {
                    $append[] = $coordinates + [$dimensionName => (string) $preset['values'][0]];
                }
            }
            $result = $append;
        }

        return $result;
    }
}
