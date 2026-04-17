<?php

declare(strict_types=1);

namespace Neos\MetaData;

use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoints;
use Neos\MetaData\Domain\Dto\MetaDataEditorDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;
use Neos\MetaData\Domain\Dto\MetaDataPropertyUiDefinition;
use Neos\MetaData\Storage\MetaDataStorage;
use Neos\MetaData\Storage\MetaDataStorageProviderDbalAdapter;

final readonly class MetaDataManagerFactory
{
    public function __construct(
        private array $metaDataPropertiesConfiguration,
        private MetaDataStorage $metaDataStorageProvider,
        private DimensionSpacePointProvider $dimensionSpacePointProvider,
    )
    {
    }

    public function create(): MetaDataManager
    {
        return new MetaDataManager(
            $this->dimensionSpacePointProvider,
            self::parseMetaDataPropertyDefinitions($this->metaDataPropertiesConfiguration),
            $this->metaDataStorageProvider,
        );
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
