<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Fixtures;

use Neos\MetaData\Storage\MetaDataStorage;
use Neos\MetaData\Storage\MetaDataStorageMaintenance;

/**
 * A storage that also supports maintenance, as {@see MetaDataStorageProviderDbalAdapter} does.
 *
 * This exists only so that a single test double can be created for both interfaces – PHPUnit 9 cannot
 * mock an intersection of them.
 */
interface MaintainableMetaDataStorage extends MetaDataStorage, MetaDataStorageMaintenance
{
}
