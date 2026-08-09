<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Support\AbandonedCartReminderBuilder;

final class AbandonedCartReminderBuilderTest extends TestCase
{
    public function testBuildsASubjectAndABodyListingEveryItem(): void
    {
        $message = (new AbandonedCartReminderBuilder())->build(
            [
                ['name' => 'Okul Önlüğü', 'quantity' => 2],
                ['name' => 'Spor Ayakkabı', 'quantity' => 1],
            ],
            'https://example.test/sepet'
        );

        self::assertSame('Sepetinizde ürünler sizi bekliyor - Seviye Commerce Platform', $message->subject);
        self::assertStringContainsString('Okul Önlüğü (adet: 2)', $message->body);
        self::assertStringContainsString('Spor Ayakkabı (adet: 1)', $message->body);
        self::assertStringContainsString('https://example.test/sepet', $message->body);
    }

    public function testEmptyItemListStillProducesAWellFormedMessage(): void
    {
        $message = (new AbandonedCartReminderBuilder())->build([], 'https://example.test/sepet');

        self::assertStringContainsString('https://example.test/sepet', $message->body);
    }
}
