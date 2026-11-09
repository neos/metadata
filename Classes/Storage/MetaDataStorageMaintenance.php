<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

/**
 * Optional capability of a {@see MetaDataStorage} that allows stored values to be inspected and removed
 * regardless of scope, as required by the `assetmetadata:repair` command.
 *
 * This is deliberately kept out of {@see MetaDataStorage} so that implementing that interface stays
 * cheap. Consumers must check for this interface and degrade gracefully if a storage does not implement
 * it.
 */
interface MetaDataStorageMaintenance
{
    /**
     * All stored values, in no particular order.
     *
     * Implementations should stream rather than materialize the whole result, as this can cover the
     * complete table.
     *
     * @return iterable<MetaDataStoredValue>
     */
    public function findAllStoredValues(): iterable;

    /**
     * @return int the number of values that were removed
     */
    public function deleteStoredValues(MetaDataStoredValue ...$storedValues): int;
}
