<?php

declare(strict_types=1);

namespace Neos\MetaData\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\MetaDataManager;

final class AssetMetaDataMigrationCommandController extends CommandController
{
    public function __construct(
        private readonly MetaDataManager $metaDataManager,
        private readonly AssetRepository $assetRepository,
    ) {
        parent::__construct();
    }

    public function migrateExistingAssetPropertiesCommand(): void
    {
        /** @var Asset $asset */
        foreach ($this->assetRepository->findAll() as $asset) {
            $caption = $asset->getCaption();
            $copyrightNotice = $asset->getCopyrightNotice();
            $metaDataAssetReference = MetaDataAssetReference::create($asset->assetSourceIdentifier, $asset->getIdentifier());

            if (!empty($caption)) {
                $this->metaDataManager->setMetaDataPropertyValue(
                    $metaDataAssetReference,
                    MetaDataPropertyName::fromString('caption'),
                    $caption,
                );
            }
            if (!empty($copyrightNotice)) {
                $this->metaDataManager->setMetaDataPropertyValue(
                    $metaDataAssetReference,
                    MetaDataPropertyName::fromString('copyright'),
                    $copyrightNotice,
                );
            }
        }
    }
}
