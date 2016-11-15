<?php
namespace Neos\MetaData\Mapper;

/*
 * This file is part of the Neos.MetaData package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\MetaData\Domain\Collection\MetaDataCollection;
use TYPO3\Media\Domain\Model\Asset;

/**
 * Meta Data Mapper Interface
 */
interface MetaDataMapperInterface
{
    /**
     * @param Asset $asset
     * @param MetaDataCollection $metaDataCollection
     *
     * @return void
     */
    public function mapMetaData(Asset $asset, MetaDataCollection $metaDataCollection);
}
