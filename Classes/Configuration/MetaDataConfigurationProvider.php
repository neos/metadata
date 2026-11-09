<?php
declare(strict_types=1);

namespace Neos\MetaData\Configuration;

use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;

/**
 * Provider for global property configuration (usually from settings (YAML))
 */
interface MetaDataConfigurationProvider
{
    public function getPropertyConfiguration(): MetaDataPropertyDefinitions;
}
