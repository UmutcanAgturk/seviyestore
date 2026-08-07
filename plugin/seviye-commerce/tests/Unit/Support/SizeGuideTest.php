<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Support\SizeGuide;
use Seviye\Commerce\Support\SizeGuideRow;

final class SizeGuideTest extends TestCase
{
    public function testParseReturnsEmptyListForNullOrBlankInput(): void
    {
        self::assertSame([], SizeGuide::parse(null));
        self::assertSame([], SizeGuide::parse(''));
        self::assertSame([], SizeGuide::parse('   '));
    }

    public function testParseReturnsEmptyListForInvalidJson(): void
    {
        self::assertSame([], SizeGuide::parse('not json'));
        self::assertSame([], SizeGuide::parse('{"not":"a list"}'));
    }

    public function testSerializeThenParseRoundTrips(): void
    {
        $rows = [
            new SizeGuideRow('S', 4, 5, 100, 110),
            new SizeGuideRow('M', null, null, 111, 120),
        ];

        $parsed = SizeGuide::parse(SizeGuide::serialize($rows));

        self::assertCount(2, $parsed);
        self::assertSame('S', $parsed[0]->label);
        self::assertSame(4, $parsed[0]->ageMin);
        self::assertSame(5, $parsed[0]->ageMax);
        self::assertSame(100, $parsed[0]->heightMinCm);
        self::assertSame(110, $parsed[0]->heightMaxCm);
        self::assertSame('M', $parsed[1]->label);
        self::assertNull($parsed[1]->ageMin);
        self::assertNull($parsed[1]->ageMax);
    }

    public function testFromArrayCoercesEmptyStringsToNull(): void
    {
        $row = SizeGuideRow::fromArray(['label' => 'L', 'age_min' => '', 'height_min_cm' => '130']);

        self::assertSame('L', $row->label);
        self::assertNull($row->ageMin);
        self::assertSame(130, $row->heightMinCm);
    }

    public function testSerializeEmptyListProducesEmptyJsonArray(): void
    {
        self::assertSame('[]', SizeGuide::serialize([]));
    }
}
