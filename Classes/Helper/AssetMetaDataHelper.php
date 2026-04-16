<?php
declare(strict_types=1);

namespace Neos\MetaData\Helper;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Media\Domain\Model\Asset;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValues;
use Neos\MetaData\MetaDataManager;

class AssetMetaDataHelper implements ProtectedContextAwareInterface
{

    public function __construct(
        protected MetaDataManager $metaDataManager,
    )
    {
    }

    public function getMetaData(Asset $asset): array
    {
        $propertyValues = $this->metaDataManager->getMetaDataPropertyValues($asset->getIdentifier());
        $result = [];
        foreach ($propertyValues as $propertyName => $propertyValue) {
            $result[$propertyName->value] = $propertyValue?->value;
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
