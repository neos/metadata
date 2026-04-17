<?php
declare(strict_types=1);

namespace Neos\MetaData\Configuration;

use Neos\Flow\I18n\Translator;

class MetaDataConfigurationProviderYamlAdapterFactory
{
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
