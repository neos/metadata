<?php

declare(strict_types=1);

namespace Neos\MetaData\Domain\Dto;

use JsonException;
use Stringable;

/**
 * A point in the dimension space with coordinates DimensionName => DimensionValue.
 * E.g.: ["language" => ["es"], "country" => ["ar"]]
 */
final readonly class MetaDataDimensionSpacePoint implements Stringable {

    /**
     * @param array<string,string[]> $coordinates
     * @param string $hash
     */
    private function __construct(
        public array $coordinates,
        public string $hash,
    ) {
    }

    /**
     * @param array<string,string[]> $coordinates
     */
    private static function hashCoordinates(array $coordinates): string
    {
        $identityComponents = $coordinates;
        ksort($identityComponents);
        try {
            return md5(json_encode($identityComponents, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new \InvalidArgumentException('Failed to hash coordinates: ' . $e->getMessage(), 1776251973, $e);
        }
    }

    /**
     * @param array<string,string[]> $coordinates
     */
    public static function fromCoordinates(array $coordinates): self
    {
        return new self(
            $coordinates,
            self::hashCoordinates($coordinates),
        );
    }

    public function equals(self $other): bool
    {
        return $this->hash === $other->hash;
    }

    public function __toString(): string
    {
        return json_encode($this->coordinates);
    }
}
