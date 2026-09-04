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
     * The effective metadata of the given asset by property name, with dimension fallbacks applied
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
     * The effective metadata property value of the given asset and property name, with dimension fallbacks applied
     *
     * @param array<string,string> $coordinates dimension coordinates, e.g. ['language' => 'de']. Empty = the default dimension
     * @return string|int|bool|null Value of the metadata property or NULL if it was not set (or explicitly reset)
     */
    public function getMetaDataProperty(Asset $asset, string $propertyName, array $coordinates = []): string|int|bool|null
    {
        return $this->metaDataManager->getMetaDataPropertyValue(
            MetaDataAssetReference::create($asset->assetSourceIdentifier, $asset->getIdentifier()),
            $propertyName,
            $coordinates === [] ? null : MetaDataDimensionSpacePoint::fromCoordinates($coordinates),
        )->value;
    }

    public function allowsCallOfMethod($methodName): true
    {
        return true;
    }
}
