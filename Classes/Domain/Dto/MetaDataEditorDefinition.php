<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

final readonly class MetaDataEditorDefinition {

    /**
     * @param string|null $editorType Editor type (e.g. "Neos.Neos/Inspector/Editors/TextAreaEditor")
     * @param array<mixed> $options Editor specific options (e.g. ['rows' => 3])
     */
    private function __construct(
        public string|null $editorType,
        public array $options,
    ) {
    }

    public static function default(): self
    {
        return new self(null, []);
    }

    /**
     * @param array<mixed> $options Editor specific options (e.g. ['rows' => 3])
     */
    public static function create(string|null $editorType, array $options): self
    {
        return new self($editorType, $options);
    }
}
