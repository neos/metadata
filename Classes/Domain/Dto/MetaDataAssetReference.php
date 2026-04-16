<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use Neos\Media\Domain\Model\Asset;

/**
 * The global identity of an asset, consisting of its Asset Source ID and the Asset ID wihtin that source
 */
final readonly class MetaDataAssetReference
{
    /**
     * @param string $assetSourceId identifier of the asset source as configured at `Neos.Media.assetSources`
     * @param string $assetId identifier of the asset within the asset source ({@see Asset::getIdentifier()})
     */
    public function __construct(
        public string $assetSourceId,
        public string $assetId,
    ) {
    }
}
