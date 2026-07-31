<?php
declare(strict_types=1);

namespace Neos\MetaData\Helper;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Media\Domain\Model\Asset;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\MetaDataManager;

class AssetMetaDataHelper implements ProtectedContextAwareInterface
{

    public function __construct(
        protected MetaDataManager $metaDataManager,
    )
    {
    }

    /**
     * The effective metadata of the given asset by property name, i.e. with dimension fallbacks applied
     *
     * @param array<string,string> $coordinates dimension coordinates, e.g. ['language' => 'de']. Empty = the default dimension
     * @return array<string, string|int|bool|null>
     */
    public function getMetaData(Asset $asset, array $coordinates = []): array
    {
        return $this->metaDataManager->getMetaDataPropertyValues(
            MetaDataAssetReference::create($asset->assetSourceIdentifier, $asset->getIdentifier()),
            $coordinates === [] ? null : MetaDataDimensionSpacePoint::fromCoordinates($coordinates),
        )->toArray();
    }

    /**
     * @inheritDoc
     */
    public function allowsCallOfMethod($methodName)
    {
        return in_array($methodName, ['getMetaData']);
    }
}
