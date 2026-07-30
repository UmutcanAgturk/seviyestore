<?php

declare(strict_types=1);

namespace Seviye\Api\Domain;

/**
 * The one and only moment a plain API key exists outside
 * {@see \Seviye\Api\Support\ApiKeyGenerator} - returned once to the REST
 * caller who created it, never persisted or logged, never retrievable
 * again.
 */
final class GeneratedApiKey
{
    public function __construct(
        public readonly string $plainKey,
        public readonly string $prefix,
        public readonly string $hash
    ) {
    }
}
