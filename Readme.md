[![Latest Stable Version](https://poser.pugx.org/neos/metadata/v/stable)](https://packagist.org/packages/neos/metadata)
[![Total Downloads](https://poser.pugx.org/neos/metadata/downloads)](https://packagist.org/packages/neos/metadata)
[![License](https://poser.pugx.org/neos/metadata/license)](https://packagist.org/packages/neos/metadata)

# Neos.MetaData Package

This package allows extensible, dimension-aware meta data properties to be attached to assets in Neos
(or Flow).

Meta data properties are *declared in Settings*, *stored outside the asset* (in a dedicated database
table) and *resolved per dimension space point* with fallbacks along the configured content dimension
presets. This means the same asset can have a different caption or copyright notice per language or
country, without changing the `Asset` model itself.

## Requirements

* PHP 8.4 or newer
* `neos/media` 8.3, 8.4 or 9.0
* MySQL or MariaDB (the shipped Doctrine migration and the DBAL storage adapter are MySQL-specific)

## Installation

Install using composer:

    composer require neos/metadata

Afterwards apply the Doctrine migrations to create the `neos_metadata_value` table:

    ./flow doctrine:migrate

## Configuration

Meta data properties are declared below `Neos.MetaData.metaDataProperties`, keyed by property name:

```yaml
Neos:
  MetaData:
    metaDataProperties:
      'copyright':
        type: string
        globalScope: true
        ui:
          label: i18n
          inspector:
            editor: 'Neos.Neos/Inspector/Editors/TextAreaEditor'
            editorOptions:
              rows: 7
```

| Option                        | Description                                                                                                                                                    |
|-------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `type`                        | `string` (default), `integer` or `boolean`                                                                                                                       |
| `globalScope`                 | `true` = the property is meant to be shared across all dimensions (equivalent to the node property scope `nodeAggregate`), `false` (default) = per dimension       |
| `ui.label`                    | Label for the property. The literal value `i18n` looks up the translation id `properties.<propertyName>` in `Neos.MetaData:Main`; any other value is used verbatim |
| `ui.inspector.editor`         | Editor to use for this property in the Neos UI inspector                                                                                                         |
| `ui.inspector.editorOptions`  | Editor specific options                                                                                                                                          |

The package ships with three properties out of the box: `copyright`, `altText` and `caption`.

> **Note:** `type` and `globalScope` are parsed into the property definitions and exposed to consumers,
> but value conversion and scope handling are not yet enforced by `MetaDataManager` – values are
> currently written and read as provided.

Dimensions are *not* configured in this package. They are taken from the Content Repository content
dimension presets (`Neos.ContentRepository.contentDimensions`) via
`DimensionSpacePointProviderContentRepositoryAdapter`. If no content dimensions are configured, the
only valid dimension space point is the empty one.

## Usage

### PHP API

`Neos\MetaData\MetaDataManager` is the central entry point. An asset is addressed by a
`MetaDataAssetReference` (asset source id + asset id), a dimension by a `MetaDataDimensionSpacePoint`
(coordinates like `['language' => 'de']`).

```php
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\MetaDataManager;

#[Flow\Inject]
protected MetaDataManager $metaDataManager;

$assetReference = MetaDataAssetReference::create($asset->assetSourceIdentifier, $asset->getIdentifier());
$dimensionSpacePoint = MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de']);

$this->metaDataManager->setMetaDataPropertyValue($assetReference, 'caption', 'Ein Bild', $dimensionSpacePoint);
$values = $this->metaDataManager->getMetaDataPropertyValuesWithFallback($assetReference, $dimensionSpacePoint);
```

Available methods:

| Method                                          | Description                                                                                             |
|-------------------------------------------------|---------------------------------------------------------------------------------------------------------|
| `getPropertyDefinitions()`                      | All configured property definitions (name, type, scope, UI definition)                                    |
| `getDimensionSpacePointConfiguration()`         | All dimension space points resulting from the configured dimension presets                                |
| `setMetaDataPropertyValue()`                    | Sets a single property value for one dimension space point                                                |
| `unsetMetaDataPropertyValue()`                  | Removes a single property value for one dimension space point                                             |
| `getMetaDataPropertyValue()`                    | Reads a single property, resolved along the given set of dimension space points (first match wins)        |
| `getMetaDataPropertyValues()`                   | Reads all properties for exactly one dimension space point – *without* fallback                           |
| `getMetaDataPropertyValuesWithFallback()`       | Reads all properties, falling back along the dimension preset fallback chain                              |
| `getMetaDataPropertyValuesOfParentWithFallback()` | Like the above, but skips the given dimension space point – useful to display inherited values in an editor |

If the dimension space point argument is `null`, the default dimension space point (built from the
`default` value of every configured dimension) is used. Unknown property names and dimension space
points that are not allowed by the configured preset constraints lead to an `InvalidArgumentException`.

### Fusion / Eel

The Eel helper `AssetMetaData` is registered in the default Fusion context:

```
caption = ${AssetMetaData.getMetaData(asset, {language: 'de'}).caption}
```

`getMetaData(asset, coordinates = [])` returns an array of all configured property names mapped to
their values, resolved with dimension fallbacks.

### Command line

```bash
# List all meta data properties of an asset (without fallback)
./flow assetmetadata:list --asset-id <assetId> [--asset-source neos] [--dimension-space-point '{"language":"de"}']

# Set a single property
./flow assetmetadata:set --asset-id <assetId> --property caption --value "A picture" [--asset-source neos] [--dimension-space-point '{"language":"de"}']

# Remove a single property
./flow assetmetadata:unset --asset-id <assetId> --property caption [--asset-source neos] [--dimension-space-point '{"language":"de"}']
```

`--asset-source` defaults to `neos`, `--dimension-space-point` to the default dimension space point.

To move the caption and copyright notice already stored on existing `Asset` models into this package's
storage (written to the default dimension space point):

```bash
./flow assetmetadatamigration:migrateexistingassetproperties
```

## Architecture and extension points

The `MetaDataManager` is assembled by `MetaDataManagerFactory` from three interfaces, each wired to a
default implementation in `Configuration/Objects.yaml`. Replace any of them to change the behaviour:

| Interface                        | Default implementation                                    | Responsibility                                                             |
|----------------------------------|-----------------------------------------------------------|-----------------------------------------------------------------------------|
| `Storage\MetaDataStorage`        | `MetaDataStorageProviderDbalAdapter`                      | Persists property values in the `neos_metadata_value` table via Doctrine DBAL |
| `DimensionSpacePointProvider\DimensionSpacePointProvider` | `DimensionSpacePointProviderContentRepositoryAdapter` | Provides valid dimension space points, the default one and the fallback chain |
| `Configuration\MetaDataConfigurationProvider` | `MetaDataConfigurationProviderYamlAdapter`   | Turns the YAML settings into `MetaDataPropertyDefinitions`                    |

The value objects below `Classes/Domain/Dto` (`MetaDataAssetReference`, `MetaDataDimensionSpacePoint`,
`MetaDataDimensionSpacePoints`, `MetaDataPropertyName`, `MetaDataPropertyType`,
`MetaDataPropertyDefinition(s)`, `MetaDataPropertyUiDefinition`, `MetaDataEditorDefinition`,
`MetaDataPropertyValues`) are excluded from Flow's object management, so they are never proxied.

### Storage format

All values live in a single table `neos_metadata_value` with a unique index over
`asset_source_id`, `asset_id`, `property_name` and `dimension_hash`. The `dimension_hash` is the md5 of
the JSON encoded, key-sorted dimension coordinates. Rows are deleted together with their asset via a
foreign key with `ON DELETE CASCADE`.

Reading a value with fallback resolves the dimension space point chain (ordered from most specific to
most generic by fallback distance) and returns the first stored value found in that order.
