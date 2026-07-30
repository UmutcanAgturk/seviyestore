<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Channel;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Channel\PanelChannel;

final class PanelChannelTest extends TestCase
{
    public function testSendAlwaysReportsSuccess(): void
    {
        $channel = new PanelChannel();

        self::assertTrue($channel->send('12', 'Konu', 'İçerik'));
        self::assertTrue($channel->send('', '', ''));
    }
}
