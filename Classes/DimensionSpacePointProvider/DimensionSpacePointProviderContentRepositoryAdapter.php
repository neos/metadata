<?php
declare(strict_types=1);

namespace Neos\MetaData\DimensionSpacePointProvider;

use Neos\ContentRepository\Domain\Service\ConfigurationContentDimensionPresetSource;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;

class DimensionSpacePointProviderContentRepositoryAdapter implements DimensionSpacePointProvider
{
    private ?array $allPresets = null;

    public function __construct(
        private readonly ConfigurationContentDimensionPresetSource $configurationContentDimensionPresetSource,
    ) {
    }

    public function getDimensionSpacePoints(): MetaDataDimensionSpacePoints
    {
        $presets = $this->getAllPresets();
        return MetaDataDimensionSpacePoints::create(...array_map(
            fn($coords) => MetaDataDimensionSpacePoint::fromCoordinates($coords),
            $this->createAllPresetCombinations($presets)
        ));
    }

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
            $ccordinates[$dimensionName] = $dimensionConfig['default'];
        }
        return MetaDataDimensionSpacePoint::fromCoordinates($ccordinates);
    }

    public function getDimensionSpacePointChain(?MetaDataDimensionSpacePoint $dimensionSpacePoint = null
    ): MetaDataDimensionSpacePoints {
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
                if (($preset['values'][0] ?? null) === $primaryValue) {
                    $chain = $preset['values'];
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
        usort($combos, fn($a, $b) => $a['distance'] <=> $b['distance']);

        $spacePoints = array_map(
            fn($combo) => MetaDataDimensionSpacePoint::fromCoordinates($combo['coords']),
            $combos
        );

        $spacePoints[] = $this->getDefaultDimensionSpacePoint();
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
                if ($preset['values'][0] === $value) {
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

    function createAllPresetCombinations(array $input)
    {
        $result = [[]];
        foreach ($input as $key => $values) {
            $append = [];
            foreach ($values['presets'] as $value => $valueConfig) {
                foreach ($result as $data) {
                    $append[] = $data + [$key => $value];
                }
            }
            $result = $append;
        }

        return $result;
    }
}
