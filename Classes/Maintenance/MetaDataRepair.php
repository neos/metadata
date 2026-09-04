<?php

declare(strict_types=1);

namespace Neos\MetaData\Maintenance;

use Neos\MetaData\DimensionSpacePointProvider\DimensionSpacePointProvider;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\MetaDataManager;
use Neos\MetaData\Storage\MetaDataStorage;
use Neos\MetaData\Storage\MetaDataStorageMaintenance;
use Neos\MetaData\Storage\MetaDataStoredValue;
use RuntimeException;

/**
 * Detects and fixes stored values whose scope contradicts the current configuration.
 *
 * A property is either of a global scope – exactly one shared value – or localized – one value per
 * dimension space point. Since that is derived from configuration, flipping
 * `Neos.MetaData.metaDataProperties.<name>.globalScope` leaves values behind that no longer match. Such
 * values are never returned by {@see MetaDataManager} (reads only ever look up the scope a property is
 * configured for), so this is a matter of hygiene rather than of correctness.
 */
final readonly class MetaDataRepair
{
    public function __construct(
        private MetaDataManager $metaDataManager,
        private DimensionSpacePointProvider $dimensionSpacePointProvider,
        private MetaDataStorage $storage,
    ) {
    }

    /**
     * Whether the configured storage allows its values to be inspected and removed at all
     */
    public function isSupported(): bool
    {
        return $this->storage instanceof MetaDataStorageMaintenance;
    }

    /**
     * Whether any content dimension is configured.
     *
     * If none is, every stored value for a dimension looks obsolete – which is exactly what a broken or
     * half-loaded dimension configuration looks like, so pruning must be refused in that case.
     */
    public function hasConfiguredDimensions(): bool
    {
        foreach ($this->dimensionSpacePointProvider->getDimensionSpacePoints() as $dimensionSpacePoint) {
            if ($dimensionSpacePoint->coordinates !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<MetaDataRepairAction>
     */
    public function analyze(): array
    {
        $propertyDefinitions = $this->metaDataManager->getPropertyDefinitions();
        $validDimensionHashes = [];
        foreach ($this->dimensionSpacePointProvider->getDimensionSpacePoints() as $dimensionSpacePoint) {
            $validDimensionHashes[$dimensionSpacePoint->hash] = true;
        }
        $defaultChainHashes = $this->defaultChainHashes();
        $defaultDimensionHash = $defaultChainHashes[0] ?? null;

        $actions = [];
        foreach ($this->groupedStoredValues() as $storedValues) {
            $propertyName = $storedValues[0]->propertyName;
            if (!$propertyDefinitions->include($propertyName)) {
                foreach ($storedValues as $storedValue) {
                    $actions[] = new MetaDataRepairAction(MetaDataRepairActionType::deleteUndefinedProperty, $storedValue);
                }
                continue;
            }
            $globalValues = array_values(array_filter($storedValues, static fn (MetaDataStoredValue $v) => $v->global));
            $dimensionedValues = array_values(array_filter($storedValues, static fn (MetaDataStoredValue $v) => !$v->global));

            if ($propertyDefinitions->get($propertyName)->globalScope) {
                if ($dimensionedValues === []) {
                    continue;
                }
                // Only promote if there is no shared value yet – an existing one is what reads return, so it wins
                if ($globalValues === []) {
                    $actions[] = new MetaDataRepairAction(
                        MetaDataRepairActionType::promoteToGlobalScope,
                        $this->pickWinner($dimensionedValues, $defaultChainHashes),
                    );
                }
                foreach ($dimensionedValues as $storedValue) {
                    $actions[] = new MetaDataRepairAction(MetaDataRepairActionType::deleteWrongScope, $storedValue);
                }
                continue;
            }

            $hasDefaultDimensionValue = false;
            foreach ($dimensionedValues as $storedValue) {
                if ($storedValue->dimensionHash === $defaultDimensionHash) {
                    $hasDefaultDimensionValue = true;
                }
                if (!array_key_exists($storedValue->dimensionHash, $validDimensionHashes)) {
                    $actions[] = new MetaDataRepairAction(MetaDataRepairActionType::deleteObsoleteDimension, $storedValue);
                }
            }
            foreach ($globalValues as $storedValue) {
                // Only promote if the default dimension has no value yet, so live data is never overwritten
                if (!$hasDefaultDimensionValue) {
                    $actions[] = new MetaDataRepairAction(MetaDataRepairActionType::promoteToDefaultDimension, $storedValue);
                }
                $actions[] = new MetaDataRepairAction(MetaDataRepairActionType::deleteWrongScope, $storedValue);
            }
        }
        return $actions;
    }

    /**
     * Carries out the given actions. Promotions are done before deletions, because a value that is
     * promoted is usually stored in a row that is deleted afterwards.
     *
     * @param list<MetaDataRepairAction> $actions
     * @return int the number of stored values that were removed
     */
    public function apply(array $actions, bool $prune = false): int
    {
        if (!$this->storage instanceof MetaDataStorageMaintenance) {
            throw new RuntimeException(sprintf('The configured metadata storage %s does not support repairing', $this->storage::class), 1776280001);
        }
        $actions = array_values(array_filter($actions, static fn (MetaDataRepairAction $action) => $prune || !$action->type->requiresPrune()));

        foreach ($actions as $action) {
            match ($action->type) {
                MetaDataRepairActionType::promoteToGlobalScope,
                MetaDataRepairActionType::promoteToDefaultDimension => $this->metaDataManager->setMetaDataPropertyValue(
                    $action->storedValue->assetReference,
                    $action->storedValue->propertyName,
                    $action->storedValue->value,
                ),
                default => null,
            };
        }

        $deletions = array_values(array_map(
            static fn (MetaDataRepairAction $action) => $action->storedValue,
            array_filter($actions, static fn (MetaDataRepairAction $action) => $action->type->isDeletion()),
        ));
        if ($deletions === []) {
            return 0;
        }
        return $this->storage->deleteStoredValues(...$deletions);
    }

    // -----------------------

    /**
     * All stored values grouped per asset and property
     *
     * @return iterable<list<MetaDataStoredValue>>
     */
    private function groupedStoredValues(): iterable
    {
        assert($this->storage instanceof MetaDataStorageMaintenance);
        $groups = [];
        foreach ($this->storage->findAllStoredValues() as $storedValue) {
            $key = implode("\0", [
                $storedValue->assetReference->assetSourceId,
                $storedValue->assetReference->assetId,
                $storedValue->propertyName->value,
            ]);
            $groups[$key][] = $storedValue;
        }
        return $groups;
    }

    /**
     * The value to keep when consolidating several localized values into a single shared one: the one
     * the default dimension resolves to, or – if the value only exists in unrelated dimensions – the
     * first in a stable order.
     *
     * @param non-empty-list<MetaDataStoredValue> $storedValues
     * @param list<string> $defaultChainHashes
     */
    private function pickWinner(array $storedValues, array $defaultChainHashes): MetaDataStoredValue
    {
        foreach ($defaultChainHashes as $dimensionHash) {
            foreach ($storedValues as $storedValue) {
                if ($storedValue->dimensionHash === $dimensionHash) {
                    return $storedValue;
                }
            }
        }
        usort($storedValues, static fn (MetaDataStoredValue $a, MetaDataStoredValue $b) => $a->dimensionHash <=> $b->dimensionHash);
        return $storedValues[0];
    }

    /**
     * @return list<string>
     */
    private function defaultChainHashes(): array
    {
        $chain = $this->dimensionSpacePointProvider->getDimensionSpacePointChain(
            $this->dimensionSpacePointProvider->getDefaultDimensionSpacePoint()
        );
        return $chain->map(static fn (MetaDataDimensionSpacePoint $dimensionSpacePoint) => $dimensionSpacePoint->hash);
    }
}
