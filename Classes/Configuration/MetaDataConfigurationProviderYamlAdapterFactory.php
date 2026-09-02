<?php
declare(strict_types=1);

namespace Neos\MetaData\Configuration;

use Neos\Flow\I18n\Translator;

class MetaDataConfigurationProviderYamlAdapterFactory
{
    /**
     * @param array<string, array{
     *     type?: string,
     *     globalScope?: bool,
     *     ui?: array{
     *         label?: string,
     *         inspector?: array{
     *             editor?: string,
     *             editorOptions?: array<mixed>
     *         }
     *     }
     * }|null> $propertyConfiguration
     */
    public function __construct(
        private readonly array $propertyConfiguration,
        private readonly Translator $translator,
    )
    {
    }

    public function create(): MetaDataConfigurationProvider
    {
        return new MetaDataConfigurationProviderYamlAdapter($this->propertyConfiguration, $this->translator);
    }
}
