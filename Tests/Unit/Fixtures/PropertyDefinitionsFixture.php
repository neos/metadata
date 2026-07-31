<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Fixtures;

use Neos\MetaData\Domain\Dto\MetaDataEditorDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;
use Neos\MetaData\Domain\Dto\MetaDataPropertyUiDefinition;

final class PropertyDefinitionsFixture
{
    /**
     * @param array<string, bool> $globalScopeByPropertyName
     */
    public static function create(array $globalScopeByPropertyName): MetaDataPropertyDefinitions
    {
        $definitions = [];
        foreach ($globalScopeByPropertyName as $propertyName => $globalScope) {
            $definitions[] = new MetaDataPropertyDefinition(
                MetaDataPropertyName::fromString($propertyName),
                MetaDataPropertyType::string,
                $globalScope,
                new MetaDataPropertyUiDefinition($propertyName, MetaDataEditorDefinition::default()),
            );
        }
        return MetaDataPropertyDefinitions::create(...$definitions);
    }

    /**
     * `copyright` is shared by all dimensions, `caption` is localized
     */
    public static function default(): MetaDataPropertyDefinitions
    {
        return self::create(['copyright' => true, 'caption' => false]);
    }
}
