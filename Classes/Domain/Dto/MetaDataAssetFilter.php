<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * Criteria for {@see MetaDataManager::findAssets()}. Every criterion is optional and criteria are
 * combined with AND.
 *
 * Note that an omitted {@see self::$dimensionSpacePoint} means the *default* dimension space point, as
 * everywhere else in this package – not "any dimension". A search is always carried out as seen from
 * one dimension space point, so that it returns what an editor working in that dimension actually sees.
 */
final readonly class MetaDataAssetFilter
{
    /**
     * @param string|null $assetSourceId NULL matches assets of every asset source
     * @param MetaDataDimensionSpacePoint|null $dimensionSpacePoint the dimension space point the values are resolved for, NULL = the default one
     * @param string|null $searchTerm NULL matches every asset that has a value for the filtered properties at all
     * @param MetaDataPropertyNames|null $propertyNames the properties to search in, NULL = all defined ones
     */
    private function __construct(
        public ?string $assetSourceId,
        public ?MetaDataDimensionSpacePoint $dimensionSpacePoint,
        public ?string $searchTerm,
        public ?MetaDataPropertyNames $propertyNames,
    ) {
    }

    public static function create(
        ?string $assetSourceId = null,
        ?MetaDataDimensionSpacePoint $dimensionSpacePoint = null,
        ?string $searchTerm = null,
        ?MetaDataPropertyNames $propertyNames = null,
    ): self {
        return new self(
            $assetSourceId,
            $dimensionSpacePoint,
            self::normalizeSearchTerm($searchTerm),
            $propertyNames,
        );
    }

    /**
     * A search term that is empty or consists of whitespace only is treated like an omitted one, so
     * that clearing a search field behaves like not having searched rather than like searching for
     * nothing.
     */
    private static function normalizeSearchTerm(?string $searchTerm): ?string
    {
        if ($searchTerm === null) {
            return null;
        }
        $trimmed = trim($searchTerm);
        return $trimmed === '' ? null : $trimmed;
    }
}
