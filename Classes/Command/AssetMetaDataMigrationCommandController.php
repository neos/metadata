<?php

declare(strict_types=1);

namespace Neos\MetaData\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyValue;
use Neos\MetaData\MetaDataManager;

final class AssetMetaDataMigrationCommandController extends CommandController
{

    public function __construct(
        private readonly MetaDataManager $metaDataManager,
        private readonly AssetRepository $assetRepository,
    )
    {
        parent::__construct();
    }
    public function migrateExistingAssetPropertiesCommand(): void
    {
        foreach ($this->assetRepository->findAll() as $asset) {
            /** @var Asset $asset */
            $title = $asset->getTitle();
            $caption = $asset->getCaption();
            $copyrightNotice = $asset->getCopyrightNotice();
            if (!empty($title)) {
                $this->metaDataManager->setMetaDataPropertyValue(
                    $asset->getIdentifier(),
                    MetaDataPropertyName::fromString('title'),
                    MetaDataPropertyValue::parse($asset->getTitle() ?? ''),
                );
            }
            if (!empty($caption)) {
                $this->metaDataManager->setMetaDataPropertyValue(
                    $asset->getIdentifier(),
                    MetaDataPropertyName::fromString('caption'),
                    MetaDataPropertyValue::parse($asset->getCaption() ?? ''),
                );
            }
            if (!empty($copyrightNotice)) {
                $this->metaDataManager->setMetaDataPropertyValue(
                    $asset->getIdentifier(),
                    MetaDataPropertyName::fromString('copyrightNotice'),
                    MetaDataPropertyValue::parse($asset->getCopyrightNotice() ?? ''),
                );
            }
        }
    }
}
