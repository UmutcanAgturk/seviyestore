<?php

declare(strict_types=1);

namespace Seviye\Branches\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Seviye\Branches\Domain\Slug;

final class SlugTest extends TestCase
{
    public function testTransliteratesTurkishCharacters(): void
    {
        self::assertSame('izmir-subesi', Slug::fromString('İzmir Şubesi'));
        self::assertSame('kadikoy-subesi-2', Slug::fromString('Kadıköy Şubesi 2'));
        self::assertSame('goztepe-subesi', Slug::fromString('Göztepe Şubesi'));
    }

    public function testCollapsesNonAlphanumericRunsToASingleHyphen(): void
    {
        self::assertSame('a-b-c', Slug::fromString('A   --  B__C'));
    }

    public function testTrimsLeadingAndTrailingHyphens(): void
    {
        self::assertSame('branch', Slug::fromString('  --Branch--  '));
    }
}
