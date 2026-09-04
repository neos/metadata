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
            $definitions[] = self::definition($propertyName, MetaDataPropertyType::string, $globalScope);
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

    /**
     * The default definitions plus a localized `width` of type integer and a localized `featured` of
     * type boolean
     */
    public static function typed(): MetaDataPropertyDefinitions
    {
        return MetaDataPropertyDefinitions::create(
            self::definition('copyright', MetaDataPropertyType::string, true),
            self::definition('caption', MetaDataPropertyType::string, false),
            self::definition('width', MetaDataPropertyType::integer, false),
            self::definition('featured', MetaDataPropertyType::boolean, false),
        );
    }

    // -----------------------

    private static function definition(string $propertyName, MetaDataPropertyType $type, bool $globalScope): MetaDataPropertyDefinition
    {
        return new MetaDataPropertyDefinition(
            MetaDataPropertyName::fromString($propertyName),
            $type,
            $globalScope,
            new MetaDataPropertyUiDefinition($propertyName, MetaDataEditorDefinition::default()),
        );
    }
}
