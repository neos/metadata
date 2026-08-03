[![Latest Stable Version](https://poser.pugx.org/neos/metadata/v/stable)](https://packagist.org/packages/neos/metadata)
[![Total Downloads](https://poser.pugx.org/neos/metadata/downloads)](https://packagist.org/packages/neos/metadata)
[![License](https://poser.pugx.org/neos/metadata/license)](https://packagist.org/packages/neos/metadata)

# Neos.MetaData Package

This package allows extensible, dimension-aware meta data properties to be attached to assets in Neos
(or Flow).

Meta data properties are *declared in Settings*, *stored outside the asset* (in a dedicated database
table) and *resolved per dimension space point* with fallbacks along the configured content dimension
presets. This means the same asset can have a different caption per language or country, without
changing the `Asset` model itself. Properties that must not be localized – a copyright notice, say –
can be declared to have a *global scope*, giving them a single value shared by all dimensions.

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
| `globalScope`                 | `true` = a single value shared by all dimensions, `false` (default) = one value per dimension space point                                                        |
| `ui.label`                    | Label for the property. The literal value `i18n` looks up the translation id `properties.<propertyName>` in `Neos.MetaData:Main`; any other value is used verbatim |
| `ui.inspector.editor`         | Editor to use for this property in the Neos UI inspector                                                                                                         |
| `ui.inspector.editorOptions`  | Editor specific options                                                                                                                                          |

The package ships with three properties out of the box: `copyright` (global scope), `altText` and
`caption`.

> **Note:** `type` is parsed into the property definitions and exposed to consumers, but values are not
> converted according to it yet – they are written and read as provided.

Dimensions are *not* configured in this package. They are taken from the Content Repository content
dimension presets (`Neos.ContentRepository.contentDimensions`) via
`DimensionSpacePointProviderContentRepositoryAdapter`. If no content dimensions are configured, the
only valid dimension space point is the empty one.

Changing `globalScope` of a property that already has values stored leaves values behind that no longer
match its scope. Those are never returned when reading, see [`assetmetadata:repair`](#command-line).

## Usage

### PHP API

`Neos\MetaData\MetaDataManager` is the central entry point. An asset is addressed by a
`MetaDataAssetReference` (asset source id + asset id), a dimension by a `MetaDataDimensionSpacePoint`
(coordinates like `['language' => 'de']`). Wherever a dimension space point can be passed, `null` means
the default one.

```php
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\MetaDataManager;

#[Flow\Inject]
protected MetaDataManager $metaDataManager;

$assetReference = MetaDataAssetReference::create($asset->assetSourceIdentifier, $asset->getIdentifier());
$german = MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de']);

$this->metaDataManager->setMetaDataPropertyValue($assetReference, 'caption', 'Eine Katze', $german);
$values = $this->metaDataManager->getMetaDataPropertyValues($assetReference, $german);
```

| Method                                  | Description                                                                                  |
|-----------------------------------------|-----------------------------------------------------------------------------------------------|
| `getPropertyDefinitions()`              | All configured property definitions (name, type, scope, UI definition)                          |
| `getDimensionSpacePointConfiguration()` | All dimension space points resulting from the configured dimension presets                      |
| `setMetaDataPropertyValue()`            | Sets a single property value                                                                    |
| `unsetMetaDataPropertyValue()`          | Removes a single property value                                                                 |
| `getMetaDataPropertyValue()`            | The value of one property, as a `MetaDataPropertyValue`                                         |
| `getMetaDataPropertyValues()`           | The values of all defined properties, as `MetaDataPropertyValues`                               |
| `findAssets()`                          | References of the assets matching a `MetaDataAssetFilter`                                       |

Unknown property names and dimension space points that are not allowed by the configured preset
constraints lead to an `InvalidArgumentException`.

### Reading values: own, inherited and effective

There is a single read, because the three things one usually wants to know are three views of the same
answer. `MetaDataPropertyValue` carries them side by side:

| Field                   | Use case                                                                                       |
|-------------------------|--------------------------------------------------------------------------------------------------|
| `ownValue`              | Editing. The value stored for *this* dimension, so an input field does not show a fallback value that the editor did not enter |
| `inheritedValue`, `inheritedFrom` | The translation hint below that input field: what this dimension falls back to, and where it comes from |
| `value`                 | Rendering to visitors: `ownValue ?? inheritedValue`                                                |

`hasOwnValue()` and `isInherited()` are convenience predicates on top of those.

```php
$value = $this->metaDataManager->getMetaDataPropertyValue($assetReference, 'caption', $german);

$value->ownValue;        // 'Eine Katze', or NULL if only a fallback exists
$value->inheritedValue;  // 'A cat'
$value->inheritedFrom;   // MetaDataDimensionSpacePoint for ['language' => 'en']
$value->value;           // 'Eine Katze'
```

For properties with a **global scope** the value is shared by all dimensions, so it is never inherited:
`ownValue` is the shared value and `inheritedValue` is always `null`. The dimension space point that is
passed in is ignored for such properties – callers can always pass the dimension they are working in
without having to know which properties are localized.

### Finding assets

`findAssets()` returns the assets that have a matching metadata value. All criteria of a
`MetaDataAssetFilter` are optional and are combined with AND:

```php
use Neos\MetaData\Domain\Dto\MetaDataAssetFilter;
use Neos\MetaData\Domain\Dto\MetaDataPropertyNames;

$filter = MetaDataAssetFilter::create(
    searchTerm: 'cat',
    dimensionSpacePoint: MetaDataDimensionSpacePoint::fromCoordinates(['language' => 'de']),
    propertyNames: MetaDataPropertyNames::create('caption', 'altText'),
);

foreach ($this->metaDataManager->findAssets($filter) as $assetReference) {
    $assetReference->assetSourceId;
    $assetReference->assetId;
}
```

| Criterion             | Omitted means                                                                            |
|-----------------------|--------------------------------------------------------------------------------------------|
| `assetSourceId`       | assets of every asset source                                                                 |
| `dimensionSpacePoint` | the *default* dimension space point – as everywhere else in this package, **not** "any dimension" |
| `searchTerm`          | every asset that has a value for the filtered properties at all                              |
| `propertyNames`       | all defined properties                                                                       |

The search term matches if it is contained anywhere in a value, ignoring case and accents. `%` and `_`
are matched literally rather than as wildcards. A term that is empty or consists of whitespace only is
treated like an omitted one, so clearing a search field behaves like not having searched.

A value only counts if it is the one `getMetaDataPropertyValue()` would return for the filter's
dimension space point, so the search agrees with what an editor working in that dimension sees. Given
an asset with the English caption `A cat`, searching for `cat` in German finds it as long as German
inherits that caption – and stops finding it as soon as a German caption of its own is set. Properties
with a global scope are matched on their shared value regardless of the dimension space point, just
like they are read regardless of it.

The result is lazily streamed, contains each asset at most once and is ordered by asset source id and
asset id. It carries `MetaDataAssetReference`s – the identity of an asset within its asset source – not
`Asset` objects; resolving those is up to the caller, this package never touches the asset model.
Unknown property names and dimension space points that are not allowed by the configured preset
constraints lead to an `InvalidArgumentException`, as they do everywhere else.

### Fusion / Eel

The Eel helper `AssetMetaData` is registered in the default Fusion context and returns the *effective*
values:

```
caption = ${AssetMetaData.getMetaData(asset, {language: 'de'}).caption}
```

`getMetaData(asset, coordinates = [])` returns an array of all configured property names mapped to
their effective values. Empty coordinates mean the default dimension.

### Command line

```bash
# List all meta data properties of an asset, marking inherited values
./flow assetmetadata:list --asset-id <assetId> [--asset-source neos] [--dimension-space-point '{"language":"de"}']

# Set a single property
./flow assetmetadata:set --asset-id <assetId> --property caption --value "A picture" [--asset-source neos] [--dimension-space-point '{"language":"de"}']

# Remove a single property
./flow assetmetadata:unset --asset-id <assetId> --property caption [--asset-source neos] [--dimension-space-point '{"language":"de"}']
```

`--asset-source` defaults to `neos`, `--dimension-space-point` to the default dimension space point.
For properties with a global scope the dimension space point is ignored.

To move the caption and copyright notice already stored on existing `Asset` models into this package's
storage:

```bash
./flow assetmetadatamigration:migrateexistingassetproperties
```

To find and fix values whose scope contradicts the current configuration – e.g. after changing
`globalScope` of a property:

```bash
# Report only, nothing is changed
./flow assetmetadata:repair

# Apply the reported changes
./flow assetmetadata:repair --force

# Also remove values of dimensions and properties that are no longer configured
./flow assetmetadata:repair --force --prune
```

When a property became global, the value the default dimension resolves to is kept as the shared one
and the remaining ones are removed. When a property became localized, the shared value is stored for
the default dimension space point. Existing values are never overwritten. Values of unconfigured
dimensions and of undefined properties are unreachable rather than wrong, so they are only reported
until `--prune` is given – and pruning is refused altogether while no content dimension is configured,
because a broken dimension configuration would otherwise look like every value being obsolete.

## Architecture and extension points

The `MetaDataManager` is assembled by `MetaDataManagerFactory` from three interfaces, each wired to a
default implementation in `Configuration/Objects.yaml`. Replace any of them to change the behaviour:

| Interface                        | Default implementation                                    | Responsibility                                                             |
|----------------------------------|-----------------------------------------------------------|-----------------------------------------------------------------------------|
| `Storage\MetaDataStorage`        | `MetaDataStorageProviderDbalAdapter`                      | Persists property values in the `neos_metadata_value` table via Doctrine DBAL |
| `DimensionSpacePointProvider\DimensionSpacePointProvider` | `DimensionSpacePointProviderContentRepositoryAdapter` | Provides valid dimension space points, the default one and the fallback chain |
| `Configuration\MetaDataConfigurationProvider` | `MetaDataConfigurationProviderYamlAdapter`   | Turns the YAML settings into `MetaDataPropertyDefinitions`                    |

Storage implementations are deliberately dumb: they look values up by scope and must not invent any
resolution rules. Which of the returned values wins, and whether it counts as an own or an inherited
one, is decided by the `MetaDataManager`. `findAssets()` is the one place where precedence has to be
applied inside the query, because resolving it per asset in PHP would mean a query per candidate – so
the manager hands the storage the fallback chain *ordered*, from the most to the least specific
dimension space point, and the storage applies that ranking rather than deriving one. Its docblock
states so explicitly; for every other method the order is meaningless.

`Storage\MetaDataStorageMaintenance` is an *optional* interface that allows stored values to be listed
and removed regardless of scope. Only `assetmetadata:repair` needs it; a storage that does not implement
it works fine, the command reports that repairing is unsupported.

The value objects below `Classes/Domain/Dto` are excluded from Flow's object management, so they are
never proxied.

### Storage format

All values live in a single table `neos_metadata_value` with a unique index over `asset_source_id`,
`asset_id`, `property_name` and `dimension_hash`. Rows are deleted together with their asset via a
foreign key with `ON DELETE CASCADE`.

For localized properties the `dimension_hash` is the md5 of the JSON encoded, key-sorted dimension
coordinates. For properties with a global scope it is the literal string `global` – an md5 is always 32
hex characters, so the two can never collide.

For a given asset and property the table therefore holds *either* one shared value *or* one value per
dimension space point, never both. Reads always look up the scope a property is configured for, so
values of the respective other shape are unreachable and cannot surface after a configuration change.

Reading a localized property looks up the whole fallback chain in one query. The chain is ordered from
most specific to most generic by fallback distance; the first stored value along it is the effective
one, the first one after the requested dimension space point is the inherited one.

Searching works on the same chain, in a single query per search: candidate rows are matched with a
`LIKE` and then reduced to the ones that are not shadowed, using a `NOT EXISTS` anti-join that looks
for a stored value closer along the chain. Localized and global scope properties are searched in the
same statement, as two alternatives of one condition, so that an asset matching in both is still
returned once. The leading wildcard of the `LIKE` means the index cannot be used – if that ever becomes
a problem, a `FULLTEXT` index is the way out, and nothing in the public API would have to change.

## Tests

Tests are part of the regular Flow test suites:

```bash
./bin/phpunit -c Build/BuildEssentials/PhpUnit/UnitTests.xml --filter 'Neos\\MetaData'
./bin/phpunit -c Build/BuildEssentials/PhpUnit/FunctionalTests.xml --filter 'Neos\\MetaData'
```

Everything that touches stored values is tested functionally, against the real storage adapter rather
than an in-memory double: resolving a value is spread across the manager and SQL, so a second
implementation would only ever approximate it – `utf8mb4_unicode_ci` folds case and accents in ways
that PHP string functions do not. Those tests therefore require a MySQL or MariaDB test database and
are skipped on other platforms. Only `DimensionSpacePointProviderContentRepositoryAdapterTest`, which
needs no storage at all, is a unit test.
