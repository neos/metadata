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

/*
 * IPTC DataType
 * See: https://www.iptc.org/std/photometadata/specification/IPTC-PhotoMetadata
 */
class Iptc extends AbstractMetaDataDto
{
    /**
     * @var array
     */
    protected $properties = [
        'AuthorByline' => '',
        'Caption' => '',
        'CreationDate' => '',
        'DocumentTitle' => '',
    ];

    /**
     * @return string
     */
    public function getCreationDate()
    {
        return $this->properties['CreationDate'];
    }

    /**
     * @return string
     */
    public function getAuthorByLine()
    {
        return $this->properties['AuthorByline'];
    }

    /**
     * @return array
     */
    public function getCaption()
    {
        return $this->properties['Caption'];
    }

    /**
     * @return array
     */
    public function getDocumentTitle()
    {
        return $this->properties['DocumentTitle'];
    }
}