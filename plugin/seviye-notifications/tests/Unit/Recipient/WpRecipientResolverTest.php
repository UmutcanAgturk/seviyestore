<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Unit\Recipient;

use PHPUnit\Framework\TestCase;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Recipient\WpRecipientResolver;
use Seviye\Notifications\Tests\Fakes\FakeParentContactLookup;

final class WpRecipientResolverTest extends TestCase
{
    public function testPanelResolvesToTheUserIdItself(): void
    {
        $resolver = new WpRecipientResolver(new FakeParentContactLookup());

        self::assertSame('12', $resolver->resolve(12, NotificationChannel::PANEL));
    }

    public function testSmsResolvesThroughParentsPublishedPhoneLookup(): void
    {
        $parentContacts = new FakeParentContactLookup();
        $parentContacts->phones[12] = '05551234567';
        $resolver = new WpRecipientResolver($parentContacts);

        self::assertSame('05551234567', $resolver->resolve(12, NotificationChannel::SMS));
    }

    public function testSmsResolvesToNullWhenTheVeliHasNoPhoneOnFile(): void
    {
        $resolver = new WpRecipientResolver(new FakeParentContactLookup());

        self::assertNull($resolver->resolve(12, NotificationChannel::SMS));
    }

    public function testEmailResolvesToNullOutsideAWordPressRuntime(): void
    {
        // get_userdata() does not exist in this headless test environment -
        // documents the honest fallback rather than exercising the WP path.
        $resolver = new WpRecipientResolver(new FakeParentContactLookup());

        self::assertNull($resolver->resolve(12, NotificationChannel::EMAIL));
    }
}
