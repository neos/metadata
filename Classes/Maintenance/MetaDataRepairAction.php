<?php

declare(strict_types=1);

namespace Neos\MetaData\Maintenance;

use Neos\MetaData\Storage\MetaDataStoredValue;

/**
 * A single change {@see MetaDataRepair} suggests, always relating to exactly one stored value
 */
final readonly class MetaDataRepairAction
{
    public function __construct(
        public MetaDataRepairActionType $type,
        public MetaDataStoredValue $storedValue,
    ) {
    }
}
