<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use Stringable;

/**
 * Marker for values of properties with a global scope {@see MetaDataPropertyDefinition::$globalScope}.
 *
 * Such values are shared by all dimensions, so no {@see MetaDataDimensionSpacePoint} applies to them.
 * This marker is substituted by the {@see MetaDataManager} on behalf of a global property – it is never
 * passed in by a caller.
 */
final readonly class MetaDataGlobalScope implements Stringable
{
    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function __toString(): string
    {
        return 'global';
    }
}
