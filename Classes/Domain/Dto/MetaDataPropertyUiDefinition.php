<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * Definition of the UI specifics of a custom asset meta data property
 */
final class MetaDataPropertyUiDefinition
{
    public function __construct(
        public string $label,
        public MetaDataEditorDefinition $editorDefinition,
    ) {
    }
}
