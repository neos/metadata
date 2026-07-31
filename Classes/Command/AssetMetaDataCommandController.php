<?php

declare(strict_types=1);

namespace Neos\MetaData\Command;

use InvalidArgumentException;
use JsonException;
use Neos\Flow\Cli\CommandController;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Maintenance\MetaDataRepair;
use Neos\MetaData\Maintenance\MetaDataRepairAction;
use Neos\MetaData\Maintenance\MetaDataRepairActionType;
use Neos\MetaData\MetaDataManager;

final class AssetMetaDataCommandController extends CommandController
{

    public function __construct(
        private readonly MetaDataManager $metaDataManager,
        private readonly MetaDataRepair $metaDataRepair,
    )
    {
        parent::__construct();
    }

    /**
     * Sets a metadata property for an asset to a specific value
     *
     * For properties with a global scope the dimension space point is ignored, because such properties
     * have a single value that is shared by all dimensions.
     *
     * @param string $assetId ID of the asset to set the metadata property for
     * @param string $property name of the metadata property to set
     * @param string $value value of the metadata property
     * @param string|null $assetSource optional asset source - default = "neos"
     * @param string|null $dimensionSpacePoint optional dimension space point as JSON (e.g. `'{"language": "de"}') - default = the configured defaultDimensionSpacePoint
     */
    public function setCommand(string $assetId, string $property, string $value, string|null $assetSource = null, string|null $dimensionSpacePoint = null): void
    {
        $dimensionSpacePointDecoded = $dimensionSpacePoint !== null ? self::parseDimensionSpacePoint($dimensionSpacePoint) : null;
        $assetReference = MetaDataAssetReference::create($assetSource ?? 'neos', $assetId);
        $this->metaDataManager->setMetaDataPropertyValue(
            $assetReference,
            MetaDataPropertyName::fromString($property),
            $value,
            $dimensionSpacePointDecoded,
        );
        $message = sprintf('Metadata property "%s" of asset "%s" was set to "%s"', $property, $assetId, $value);
        if ($dimensionSpacePointDecoded !== null) {
            $message .= sprintf(' for dimension space point "%s"', $dimensionSpacePointDecoded);
        }
        $this->outputLine("<success>$message</success>");
    }

    /**
     * Removes a metadata property for an asset
     *
     * @param string $assetId ID of the asset to unset the metadata property for
     * @param string $property name of the metadata property to unset
     * @param string|null $assetSource optional asset source - default = "neos"
     * @param string|null $dimensionSpacePoint optional dimension space point as JSON (e.g. `'{"language": "de"}') - default = the configured defaultDimensionSpacePoint
     */
    public function unsetCommand(string $assetId, string $property, string|null $assetSource = null, string|null $dimensionSpacePoint = null): void
    {
        $dimensionSpacePointDecoded = $dimensionSpacePoint !== null ? self::parseDimensionSpacePoint($dimensionSpacePoint) : null;
        $assetReference = MetaDataAssetReference::create($assetSource ?? 'neos', $assetId);
        $this->metaDataManager->unsetMetaDataPropertyValue(
            $assetReference,
            MetaDataPropertyName::fromString($property),
            $dimensionSpacePointDecoded,
        );
        $message = sprintf('Metadata property "%s" of asset "%s" was unset', $property, $assetId);
        if ($dimensionSpacePointDecoded !== null) {
            $message .= sprintf(' for dimension space point "%s"', $dimensionSpacePointDecoded);
        }
        $this->outputLine("<success>$message</success>");
    }

    /**
     * Lists all metadata properties for an asset
     *
     * Values that stem from a fallback dimension are marked as inherited.
     *
     * @param string $assetId ID of the asset to list the metadata properties for
     * @param string|null $assetSource optional asset source - default = "neos"
     * @param string|null $dimensionSpacePoint optional dimension space point as JSON (e.g. `'{"language": "de"}') - default = the configured defaultDimensionSpacePoint
     */
    public function listCommand(string $assetId, string|null $assetSource = null, string|null $dimensionSpacePoint = null): void
    {
        $dimensionSpacePointDecoded = $dimensionSpacePoint !== null ? self::parseDimensionSpacePoint($dimensionSpacePoint) : null;
        $assetReference = MetaDataAssetReference::create($assetSource ?? 'neos', $assetId);
        $metaDataPropertyValues = $this->metaDataManager->getMetaDataPropertyValues(
            $assetReference,
            $dimensionSpacePointDecoded,
        );
        $message = sprintf('Metadata properties of asset "%s"', $assetId);
        if ($dimensionSpacePointDecoded !== null) {
            $message .= sprintf(' for dimension space point "%s"', $dimensionSpacePointDecoded);
        }
        $this->outputLine($message . ':');
        foreach ($metaDataPropertyValues as $propertyName => $propertyValue) {
            $line = sprintf('  <b>%s:</b> %s', $propertyName, $propertyValue->value ?? '-');
            if ($propertyValue->isInherited()) {
                $line .= sprintf(' <comment>(inherited from %s)</comment>', $propertyValue->inheritedFrom);
            }
            $this->outputLine($line);
        }
    }

    /**
     * Finds and fixes metadata values whose scope contradicts the current configuration
     *
     * Whether a property has a single shared value or one value per dimension is configured via
     * `Neos.MetaData.metaDataProperties.<name>.globalScope`. Changing that leaves values behind that no
     * longer match. Those are never returned when reading metadata, so this command is about tidying up
     * rather than about fixing broken reads.
     *
     * Without `--force` nothing is changed and the pending changes are merely reported.
     *
     * @param bool $force apply the changes instead of only reporting them
     * @param bool $prune also remove values of dimensions and of properties that are no longer configured
     */
    public function repairCommand(bool $force = false, bool $prune = false): void
    {
        if (!$this->metaDataRepair->isSupported()) {
            $this->outputLine('<error>The configured metadata storage does not support repairing</error>');
            $this->quit(1);
        }
        if ($prune && !$this->metaDataRepair->hasConfiguredDimensions()) {
            $this->outputLine('<error>Refusing to prune because no content dimension is configured</error>');
            $this->outputLine('Every value stored for a dimension would look obsolete, which is also what a broken dimension configuration looks like.');
            $this->quit(1);
        }

        $actions = $this->metaDataRepair->analyze();
        if ($actions === []) {
            $this->outputLine('<success>No metadata values need repairing</success>');
            return;
        }

        $this->outputScopeActions($actions);
        $this->outputPruneActions($actions, $prune);

        $applicable = array_filter($actions, static fn (MetaDataRepairAction $action) => $prune || !$action->type->requiresPrune());
        if ($applicable === []) {
            return;
        }
        if (!$force) {
            $this->outputLine();
            $this->outputLine('<comment>Nothing was changed. Re-run with --force to apply.</comment>');
            return;
        }
        $deleted = $this->metaDataRepair->apply($actions, $prune);
        $this->outputLine();
        $this->outputLine('<success>Repaired metadata values, %d value(s) were removed</success>', [$deleted]);
    }

    // -----------------------

    /**
     * @param list<MetaDataRepairAction> $actions
     */
    private function outputScopeActions(array $actions): void
    {
        $scopeActions = array_filter($actions, static fn (MetaDataRepairAction $action) => !$action->type->requiresPrune());
        if ($scopeActions === []) {
            return;
        }
        $this->outputLine('<b>Values with a scope that contradicts the property definition:</b>');
        foreach ($scopeActions as $action) {
            $storedValue = $action->storedValue;
            $description = match ($action->type) {
                MetaDataRepairActionType::promoteToGlobalScope => sprintf('keep "%s" as the shared value', $storedValue->value),
                MetaDataRepairActionType::promoteToDefaultDimension => sprintf('store "%s" for the default dimension', $storedValue->value),
                MetaDataRepairActionType::deleteWrongScope => sprintf('delete "%s" (%s)', $storedValue->value, $storedValue->global ? 'shared value' : 'dimension ' . $storedValue->dimensionHash),
                default => '',
            };
            $this->outputLine(sprintf('  %s / %s: %s', $storedValue->assetReference->assetId, $storedValue->propertyName, $description));
        }
    }

    /**
     * @param list<MetaDataRepairAction> $actions
     */
    private function outputPruneActions(array $actions, bool $prune): void
    {
        $obsoleteDimensions = 0;
        $undefinedProperties = 0;
        foreach ($actions as $action) {
            match ($action->type) {
                MetaDataRepairActionType::deleteObsoleteDimension => $obsoleteDimensions++,
                MetaDataRepairActionType::deleteUndefinedProperty => $undefinedProperties++,
                default => null,
            };
        }
        if ($obsoleteDimensions === 0 && $undefinedProperties === 0) {
            return;
        }
        $this->outputLine();
        $this->outputLine('<b>Unreachable values:</b>');
        if ($obsoleteDimensions > 0) {
            $this->outputLine(sprintf('  %d value(s) stored for a dimension that is no longer configured', $obsoleteDimensions));
        }
        if ($undefinedProperties > 0) {
            $this->outputLine(sprintf('  %d value(s) of a property that is no longer defined', $undefinedProperties));
        }
        if (!$prune) {
            $this->outputLine('  <comment>Re-run with --prune to include them.</comment>');
        }
    }

    private static function parseDimensionSpacePoint(string $dimensionSpacePoint): MetaDataDimensionSpacePoint
    {
        try {
            return MetaDataDimensionSpacePoint::fromCoordinates(json_decode($dimensionSpacePoint, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to parse dimension space point: ' . $e->getMessage(), 1776274597, $e);
        }
    }

}
