<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Channel;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Channel\WhatsAppChannel;
use Seviye\Notifications\Tests\Fakes\FakeSettingsRepository;

final class WhatsAppChannelTest extends TestCase
{
    public function testSendFailsWhenNoCredentialsAreConfigured(): void
    {
        $channel = new WhatsAppChannel(new FakeSettingsRepository());

        self::assertFalse($channel->send('05551234567', 'Konu', 'Gövde'));
    }

    public function testSendFailsWhenOnlySomeCredentialsAreConfigured(): void
    {
        $settings = new FakeSettingsRepository();
        $settings->set(WhatsAppChannel::SETTING_PHONE_NUMBER_ID, '1234567890');
        // access_token left unset.
        $channel = new WhatsAppChannel($settings);

        self::assertFalse($channel->send('05551234567', 'Konu', 'Gövde'));
    }

    /**
     * wp_remote_post() does not exist in this headless test environment -
     * documents the honest fallback (same as NetgsmSmsChannel's guard)
     * rather than exercising the real HTTP call.
     */
    public function testSendFailsOutsideAWordPressRuntimeEvenWithFullCredentials(): void
    {
        $settings = new FakeSettingsRepository();
        $settings->set(WhatsAppChannel::SETTING_PHONE_NUMBER_ID, '1234567890');
        $settings->set(WhatsAppChannel::SETTING_ACCESS_TOKEN, 'secret');
        $channel = new WhatsAppChannel($settings);

        self::assertFalse($channel->send('05551234567', 'Konu', 'Gövde'));
    }

    /**
     * @dataProvider phoneNumberProvider
     */
    public function testNormalizePhoneProducesDigitsWithCountryCodeAndNoLeadingZero(
        string $input,
        string $expected
    ): void {
        $channel = new WhatsAppChannel(new FakeSettingsRepository());

        self::assertSame($expected, $channel->normalizePhone($input));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function phoneNumberProvider(): array
    {
        return [
            'local with leading zero' => ['05551234567', '905551234567'],
            'already with country code' => ['+905551234567', '905551234567'],
            'with spaces and dashes' => ['0 555 123 45 67', '905551234567'],
            'without leading zero' => ['5551234567', '905551234567'],
        ];
    }
}
