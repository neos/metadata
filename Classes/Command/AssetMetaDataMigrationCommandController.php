<?php

declare(strict_types=1);

namespace Neos\MetaData\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\MetaData\ConfigurationProvider\ArrayMetaDataConfigurationProvider;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\MetaDataManager;

final class AssetMetaDataMigrationCommandController extends CommandController
{

    public function __construct(
        private readonly MetaDataManager $metaDataManager,
        private readonly ArrayMetaDataConfigurationProvider $metaDataConfigurationProvider,
        private readonly AssetRepository $assetRepository,
    )
    {
        parent::__construct();
    }
    public function migrateExistingAssetPropertiesCommand(): void
    {
        $metaDataDimensionSpacePoints = $this->metaDataConfigurationProvider->getConfiguration()->dimensions;
        foreach ($this->assetRepository->findAll() as $asset) {
            /** @var Asset $asset */
            $title = $asset->getTitle();
            $caption = $asset->getCaption();
            $copyrightNotice = $asset->getCopyrightNotice();
            $metaDataAssetReference = MetaDataAssetReference::fromAsset($asset);
            foreach ($metaDataDimensionSpacePoints as $dimensionSpacePoint) {
                if (!empty($title)) {
                    $this->metaDataManager->setMetaDataPropertyValue(
                        $metaDataAssetReference,
                        MetaDataPropertyName::fromString('title'),
                        $asset->getTitle(),
                        $dimensionSpacePoint,
                    );
                }
                if (!empty($caption)) {
                    $this->metaDataManager->setMetaDataPropertyValue(
                        $metaDataAssetReference,
                        MetaDataPropertyName::fromString('caption'),
                        $asset->getCaption(),
                        $dimensionSpacePoint,
                    );
                }
                if (!empty($copyrightNotice)) {
                    $this->metaDataManager->setMetaDataPropertyValue(
                        $metaDataAssetReference,
                        MetaDataPropertyName::fromString('copyrightNotice'),
                        $asset->getCopyrightNotice(),
                        $dimensionSpacePoint,
                    );
                }
            }
        }
    }
}
