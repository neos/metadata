<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * The global identity of an asset, consisting of its Asset Source ID and the Asset ID wihtin that source
 */
final readonly class MetaDataAssetReference
{
    /**
     * @param string $assetSourceId identifier of the asset source as configured at `Neos.Media.assetSources`
     * @param string $assetId identifier of the asset within the asset source ({@see Asset::getIdentifier()})
     */
    private function __construct(
        public string $assetSourceId,
        public string $assetId,
    ) {
    }

    public static function create(
        string $assetSourceId,
        string $assetId,
    ) {
        return new self($assetSourceId, $assetId);
    }
}
