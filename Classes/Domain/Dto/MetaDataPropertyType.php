<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

/**
 * Type of a custom asset metadata property
 */
enum MetaDataPropertyType {
    case string;
    case integer;
    case boolean;
}
