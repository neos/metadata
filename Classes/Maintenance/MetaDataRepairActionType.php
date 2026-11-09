<?php

declare(strict_types=1);

namespace Neos\MetaData\Maintenance;

/**
 * The kind of change {@see MetaDataRepair} suggests for a single stored value
 */
enum MetaDataRepairActionType
{
    /**
     * The property has a global scope but the value is stored for a dimension – re-store it as the
     * shared value
     */
    case promoteToGlobalScope;

    /**
     * The property is localized but the value is stored as a shared one – re-store it for the default
     * dimension space point
     */
    case promoteToDefaultDimension;

    /**
     * The value is stored with a scope that contradicts the property definition and is therefore
     * unreachable
     */
    case deleteWrongScope;

    /**
     * The value is stored for a dimension space point that is no longer configured
     */
    case deleteObsoleteDimension;

    /**
     * The value belongs to a property that is no longer defined
     */
    case deleteUndefinedProperty;

    /**
     * Whether this action removes data that is merely unreachable rather than contradictory, and is
     * therefore only carried out when pruning was explicitly requested
     */
    public function requiresPrune(): bool
    {
        return $this === self::deleteObsoleteDimension || $this === self::deleteUndefinedProperty;
    }

    public function isDeletion(): bool
    {
        return $this !== self::promoteToGlobalScope && $this !== self::promoteToDefaultDimension;
    }
}
