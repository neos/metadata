<?php
declare(strict_types=1);

namespace Neos\MetaData\Configuration;

use Neos\Flow\I18n\Translator;
use Neos\MetaData\Domain\Dto\MetaDataEditorDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;
use Neos\MetaData\Domain\Dto\MetaDataPropertyUiDefinition;

class MetaDataConfigurationProviderYamlAdapter implements MetaDataConfigurationProvider
{
    public function __construct(
        private readonly array $propertyConfiguration,
        private readonly Translator $translator,
    )
    {
    }

    public function getPropertyConfiguration(): MetaDataPropertyDefinitions
    {
        $propertyDefinitions = [];
        foreach ($this->propertyConfiguration as $propertyName => $propertyDefinition) {
            if ($propertyDefinition === null) {
                // allows to disable property definitions that are configured elsewhere
                continue;
            }
            $propertyDefinitions[] = new MetaDataPropertyDefinition(
                MetaDataPropertyName::fromString($propertyName),
                match ($propertyDefinition['type'] ?? null) {
                    'integer' => MetaDataPropertyType::integer,
                    'boolean' => MetaDataPropertyType::boolean,
                    default => MetaDataPropertyType::string,
                },
                $propertyDefinition['globalScope'] ?? false,
                new MetaDataPropertyUiDefinition(
                    $this->translatePropertyName($propertyName, $propertyDefinition['ui']['label'] ?? null),
                    MetaDataEditorDefinition::create(
                        editorType: $propertyDefinition['ui']['inspector']['editor'] ?? null,
                        options: $propertyDefinition['ui']['inspector']['editorOptions'] ?? [],
                    )
                )
            );
        }
        return MetaDataPropertyDefinitions::create(...$propertyDefinitions);
    }

    // -----------------------

    private function translatePropertyName(string $propertyName, ?string $label): string
    {
        if ($label === 'i18n') {
            $translationShortHandString = sprintf('properties.%s', $propertyName);
             return $this->translator->translateById($translationShortHandString, [], null, null, 'Main', 'Neos.MetaData') ?? $propertyName;
        } elseif ($label !== null) {
            return $label;
        }
        return $propertyName;
    }
}
