<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Support\WeeklyDigestBuilder;

final class WeeklyDigestBuilderTest extends TestCase
{
    public function testBuildsASubjectAndABodyContainingEveryFigure(): void
    {
        $message = (new WeeklyDigestBuilder())->build(12, 4590.5, 1250.75, 3);

        self::assertSame('Haftalık Özet - Seviye Commerce Platform', $message->subject);
        self::assertStringContainsString('12', $message->body);
        self::assertStringContainsString('4.590,50', $message->body);
        self::assertStringContainsString('1.250,75', $message->body);
        self::assertStringContainsString('3', $message->body);
    }

    public function testZeroFiguresStillProduceAWellFormedMessage(): void
    {
        $message = (new WeeklyDigestBuilder())->build(0, 0.0, 0.0, 0);

        self::assertStringContainsString('0,00', $message->body);
        self::assertSame(4, substr_count($message->body, "\n") + 1);
    }
}
