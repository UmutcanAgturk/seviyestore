<?php

declare(strict_types=1);

namespace Seviye\Api\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Api\Support\ApiKeyGenerator;

final class ApiKeyGeneratorTest extends TestCase
{
    public function testGenerateProducesAKeyWithTheExpectedPrefix(): void
    {
        $generated = ApiKeyGenerator::generate();

        self::assertStringStartsWith('scp_live_', $generated->plainKey);
        self::assertSame(9 + 48, strlen($generated->plainKey));
    }

    public function testGenerateProducesADifferentKeyEachTime(): void
    {
        $first = ApiKeyGenerator::generate();
        $second = ApiKeyGenerator::generate();

        self::assertNotSame($first->plainKey, $second->plainKey);
    }

    public function testHashIsDeterministicForTheSameKey(): void
    {
        $generated = ApiKeyGenerator::generate();

        self::assertSame(ApiKeyGenerator::hash($generated->plainKey), $generated->hash);
        self::assertSame(64, strlen($generated->hash));
    }

    public function testHashDiffersForDifferentKeys(): void
    {
        $first = ApiKeyGenerator::generate();
        $second = ApiKeyGenerator::generate();

        self::assertNotSame($first->hash, $second->hash);
    }

    public function testDisplayPrefixIsAShortNonSecretSlice(): void
    {
        $generated = ApiKeyGenerator::generate();

        self::assertSame('scp_live_' . substr($generated->plainKey, 9, 6), $generated->prefix);
        self::assertLessThan(strlen($generated->plainKey), strlen($generated->prefix));
    }
}
