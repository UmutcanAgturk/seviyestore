<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Support\ShopShowcase;

final class ShopShowcaseTest extends TestCase
{
    public function testParseReturnsEmptyShowcaseForNullOrBlankInput(): void
    {
        self::assertTrue(ShopShowcase::parse(null)->isEmpty());
        self::assertTrue(ShopShowcase::parse('')->isEmpty());
        self::assertTrue(ShopShowcase::parse('   ')->isEmpty());
    }

    public function testParseReturnsEmptyShowcaseForInvalidJson(): void
    {
        self::assertTrue(ShopShowcase::parse('not json')->isEmpty());
    }

    public function testSerializeThenParseRoundTrips(): void
    {
        $showcase = new ShopShowcase('Yeni Sezon Başladı', 'Okul kıyafetlerinde %20 indirim', 42);

        $parsed = ShopShowcase::parse(ShopShowcase::serialize($showcase));

        self::assertSame('Yeni Sezon Başladı', $parsed->heading);
        self::assertSame('Okul kıyafetlerinde %20 indirim', $parsed->subheading);
        self::assertSame(42, $parsed->imageAttachmentId);
        self::assertFalse($parsed->isEmpty());
    }

    public function testFromArrayCoercesInvalidImageIdToNull(): void
    {
        $showcase = ShopShowcase::fromArray(['heading' => 'Merhaba', 'image_attachment_id' => 0]);

        self::assertSame('Merhaba', $showcase->heading);
        self::assertNull($showcase->imageAttachmentId);
    }

    public function testSerializeEmptyShowcaseProducesEmptyJsonObject(): void
    {
        self::assertSame(
            '{"heading":"","subheading":"","image_attachment_id":null,"seasonal_theme":""}',
            ShopShowcase::serialize(new ShopShowcase())
        );
    }

    public function testDefaultConstructedShowcaseIsEmpty(): void
    {
        self::assertTrue((new ShopShowcase())->isEmpty());
    }

    public function testSeasonalThemeAloneMakesShowcaseNonEmpty(): void
    {
        $showcase = new ShopShowcase(seasonalTheme: ShopShowcase::SEASONAL_THEME_BACK_TO_SCHOOL);

        self::assertFalse($showcase->isEmpty());
        self::assertSame(ShopShowcase::SEASONAL_THEME_BACK_TO_SCHOOL, $showcase->seasonalTheme);
    }

    public function testFromArrayRejectsUnknownSeasonalTheme(): void
    {
        $showcase = ShopShowcase::fromArray(['seasonal_theme' => 'halloween']);

        self::assertSame('', $showcase->seasonalTheme);
    }
}
