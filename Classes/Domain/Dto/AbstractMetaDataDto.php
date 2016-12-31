<?php
namespace Neos\MetaData\Domain\Dto;

/*
 * This file is part of the Neos.MetaData package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Utility\Arrays;

abstract class AbstractMetaDataDto
{
    /**
     * @var array
     */
    protected $properties = [];

    /**
     * @param array $properties
     */
    public function __construct(array $properties)
    {
        $this->properties = Arrays::arrayMergeRecursiveOverrule($this->properties, $properties);
    }
}
