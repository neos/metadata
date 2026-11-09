<?php

declare(strict_types=1);

namespace Neos\MetaData\Storage;

use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;

/**
 * A single stored metadata value as returned by {@see MetaDataStorageMaintenance::findAllStoredValues()}
 *
 * The dimension hash cannot be turned back into a {@see MetaDataDimensionSpacePoint} – it is a one-way
 * hash – so it is exposed as an opaque handle that can be compared with
 * {@see MetaDataDimensionSpacePoint::$hash} and passed back to
 * {@see MetaDataStorageMaintenance::deleteStoredValues()}.
 */
final readonly class MetaDataStoredValue
{
    /**
     * @param string $dimensionHash opaque handle, meaningless if $global is TRUE
     * @param bool $global whether this value is stored for a global scope, i.e. shared by all dimensions
     */
    public function __construct(
        public MetaDataAssetReference $assetReference,
        public MetaDataPropertyName $propertyName,
        public string $dimensionHash,
        public bool $global,
        public string|int|bool $value,
    ) {
    }
}
