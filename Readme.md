[![Latest Stable Version](https://poser.pugx.org/neos/metadata/v/stable)](https://packagist.org/packages/neos/metadata)
[![Total Downloads](https://poser.pugx.org/neos/metadata/downloads)](https://packagist.org/packages/neos/metadata)
[![License](https://poser.pugx.org/neos/metadata/license)](https://packagist.org/packages/neos/metadata)

# Neos.MetaData Package

This package provides data types and interfaces to handle meta data for assets in Neos (or Flow).

## Installation

Install using composer:

    composer require neos/metadata  

If you install a package that depends on this package, you should not need to require it manually,
though. Some related packages are:

- [`neos/metadata-extractor`](https://github.com/neos/metadata-extractor): Provides CLI and realtime
  meta data extraction on assets
- [`neos/metadata-contentrepositoryadapter`](https://github.com/neos/metadata-contentrepositoryadapter):
  Handles the mapping of meta data DTOs to the Neos Content Repository

## Configuration

The provided asset meta data mapper is configured with Eel expressions to determine the source of
mapped data. Check the setup below `Neos.MetaData.metaDataMapping`.

## Usage

The package does not in itself change the way metadata is handled. Instead it provides ways for
other packages to interact with meta data of assets.

### Defined Meta Data Mappers

* **AssetModelMetaDataMapper**: Maps meta data to `Asset` models from `neos/media`. Supported are title,  
  caption, copyright notice, tags and collections (see configuration above).

### Defined Data Transfer Objects

* **Asset**: The asset DTO provides basic data about the asset, like original file name.
* **IPTC**: Image meta data, title and description of the photograph and the author. For
  further specifications see https://www.iptc.org/std/photometadata/specification/IPTC-PhotoMetadata.
* **EXIF**: Exchangeable image file format for digital still cameras, the technical meta data of an image.
  For further specifications see http://www.cipa.jp/std/documents/e/DC-008-Translation-2016-E.pdf.
