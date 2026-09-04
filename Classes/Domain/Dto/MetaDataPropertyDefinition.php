<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * Definition of a custom asset metadata property {@see MetaDataPropertyDefinitions}
 */
final readonly class MetaDataPropertyDefinition
{
    /**
     * @param bool $globalScope TRUE = equivalent to Node Property Scope "nodeAggregate", FALSE = equivalent to Node Property Scope "node"
     */
    public function __construct(
        public MetaDataPropertyName $name,
        public MetaDataPropertyType $type,
        public bool $globalScope,
        public ?MetaDataPropertyUiDefinition $ui = null,
        public ?array $options = null,
    ) {
    }
}
