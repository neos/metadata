<?php

declare(strict_types=1);

namespace Neos\MetaData\ConfigurationProvider;

use Neos\MetaData\Domain\Dto\MetaDataConfiguration;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePointSet;
use Neos\MetaData\Domain\Dto\MetaDataEditorDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;
use Neos\MetaData\Domain\Dto\MetaDataPropertyUiDefinition;
use Webmozart\Assert\Assert;

final readonly class ArrayMetaDataConfigurationProvider
{

    public function __construct(
        private array $configuration,
    ) {
    }

    public function getConfiguration(): MetaDataConfiguration
    {
        Assert::isArray($this->configuration['metaDataProperties']);
        return new MetaDataConfiguration(
            defaultDimensionSpacePoint: self::parseDimensionSpacePoint($this->configuration['defaultDimensionSpacePoint'] ?? []),
            dimensions: MetaDataDimensionSpacePointSet::create(
                ...array_map(self::parseDimensionSpacePoint(...), $this->configuration['dimensions'] ?? []),
            ),
            propertyDefinitions: self::parseMetaDataPropertyDefinitions($this->configuration['metaDataProperties'] ?? [])
        );
    }

    private static function parseDimensionSpacePoint(mixed $dimensionSpacePoint): MetaDataDimensionSpacePoint
    {
        Assert::isArray($dimensionSpacePoint);
        return MetaDataDimensionSpacePoint::fromCoordinates($dimensionSpacePoint);
    }

    private static function parseMetaDataPropertyDefinitions(array $metaDataProperties): MetaDataPropertyDefinitions
    {
        $propertyDefinitions = [];
        foreach ($metaDataProperties as $propertyName => $propertyDefinition) {
            $propertyDefinitions[] = new MetaDataPropertyDefinition(
                MetaDataPropertyName::fromString($propertyName),
                match ($propertyDefinition['type'] ?? null) {
                    'integer' => MetaDataPropertyType::integer,
                    'boolean' => MetaDataPropertyType::boolean,
                    default => MetaDataPropertyType::string,
                },
                $propertyDefinition['globalScope'] ?? false,
                new MetaDataPropertyUiDefinition(
                    $propertyDefinition['ui']['label'] ?? $propertyName, // TODO support "i18n"?
                    MetaDataEditorDefinition::create(
                        editorType: $propertyDefinition['ui']['editor'] ?? null,
                        options: $propertyDefinition['ui']['editorOptions'] ?? [],
                    )
                )
            );
        }
        return MetaDataPropertyDefinitions::create(...$propertyDefinitions);
    }
}
