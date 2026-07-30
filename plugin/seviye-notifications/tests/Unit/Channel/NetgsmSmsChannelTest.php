<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Channel;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Channel\NetgsmSmsChannel;
use Seviye\Notifications\Tests\Fakes\FakeSettingsRepository;

final class NetgsmSmsChannelTest extends TestCase
{
    public function testSendFailsWhenNoCredentialsAreConfigured(): void
    {
        $channel = new NetgsmSmsChannel(new FakeSettingsRepository());

        self::assertFalse($channel->send('05551234567', 'Konu', 'Gövde'));
    }

    public function testSendFailsWhenOnlySomeCredentialsAreConfigured(): void
    {
        $settings = new FakeSettingsRepository();
        $settings->set(NetgsmSmsChannel::SETTING_USERCODE, '1234567');
        // password/msgheader left unset.
        $channel = new NetgsmSmsChannel($settings);

        self::assertFalse($channel->send('05551234567', 'Konu', 'Gövde'));
    }

    /**
     * wp_remote_post() does not exist in this headless test environment -
     * documents the honest fallback (same as EmailChannel's wp_mail()
     * guard) rather than exercising the real HTTP call.
     */
    public function testSendFailsOutsideAWordPressRuntimeEvenWithFullCredentials(): void
    {
        $settings = new FakeSettingsRepository();
        $settings->set(NetgsmSmsChannel::SETTING_USERCODE, '1234567');
        $settings->set(NetgsmSmsChannel::SETTING_PASSWORD, 'secret');
        $settings->set(NetgsmSmsChannel::SETTING_HEADER, 'SEVIYE');
        $channel = new NetgsmSmsChannel($settings);

        self::assertFalse($channel->send('05551234567', 'Konu', 'Gövde'));
    }

    /**
     * @dataProvider phoneNumberProvider
     */
    public function testNormalizePhoneProducesAnMsisdnStartingWithZero(string $input, string $expected): void
    {
        $channel = new NetgsmSmsChannel(new FakeSettingsRepository());

        self::assertSame($expected, $channel->normalizePhone($input));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function phoneNumberProvider(): array
    {
        return [
            'already normalized' => ['05551234567', '05551234567'],
            'with country code' => ['+905551234567', '05551234567'],
            'with spaces and dashes' => ['0 555 123 45 67', '05551234567'],
            'without leading zero' => ['5551234567', '05551234567'],
        ];
    }

    /**
     * @dataProvider successCodeProvider
     */
    public function testIsSuccessCode(string $response, bool $expected): void
    {
        $channel = new NetgsmSmsChannel(new FakeSettingsRepository());

        self::assertSame($expected, $channel->isSuccessCode($response));
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function successCodeProvider(): array
    {
        return [
            'queued immediately' => ["00 1234567890\n", true],
            'queued for scheduled sending' => ['01 1234567890', true],
            'invalid credentials' => ['30', false],
            'message text error' => ['40', false],
            'empty response' => ['', false],
        ];
    }
}
