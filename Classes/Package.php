<?php

declare(strict_types=1);

namespace Neos\MetaData;

/*
 * This file is part of the Neos.MetaData package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Service\AssetService;
use Neos\MetaData\Domain\Dto\MetaDataAssetReference;

class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap): void
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(
            AssetService::class,
            'assetRemoved',
            function (AssetInterface $asset) use ($bootstrap) {
                if (!$asset instanceof Asset || !$asset->getAssetSourceIdentifier()) {
                    return;
                }

                $assetReference = MetaDataAssetReference::create(
                    $asset->getAssetSourceIdentifier(),
                    $asset->getIdentifier()
                );

                /** @var MetaDataManager $metaDataManager */
               $metaDataManager = $bootstrap->getObjectManager()->get(MetaDataManager::class);
               $metaDataManager->unsetMetaDataPropertyValues($assetReference);
            }
        );
    }
}
