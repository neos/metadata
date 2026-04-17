<?php
declare(strict_types=1);

namespace Neos\MetaData\Helper;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Media\Domain\Model\Asset;
use Neos\MetaData\ConfigurationProvider\ArrayMetaDataConfigurationProvider;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataDimensionSpacePoint;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValues;
use Neos\MetaData\MetaDataManager;

class AssetMetaDataHelper implements ProtectedContextAwareInterface
{

    public function __construct(
        protected MetaDataManager $metaDataManager,
    )
    {
    }

    public function getMetaData(Asset $asset, array $dimensionSpacePoints): array
    {
        $propertyValues = $this->metaDataManager->getMetaDataPropertyValues(
            MetaDataAssetReference::fromAsset($asset),
            MetaDataDimensionSpacePoint::fromCoordinates($dimensionSpacePoints),
        );
        $result = [];
        foreach ($propertyValues as $propertyName => $propertyValue) {
            /** @var $propertyName MetaDataPropertyName */
            $result[$propertyName->value] = $propertyValue;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function allowsCallOfMethod($methodName)
    {
        return in_array($methodName, ['getMetaData']);
    }
}
