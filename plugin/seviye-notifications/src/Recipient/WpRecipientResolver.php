<?php

declare(strict_types=1);

namespace Seviye\Notifications\Recipient;

use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Parents\Contracts\ParentContactLookupInterface;

/**
 * EMAIL resolves through WordPress' own get_userdata() - every WP user
 * account has a user_email, so this needs no Seviye-specific data source.
 * PANEL needs no external "address" at all (the channel is the
 * scp_notifications row itself, keyed by user_id already) - the user ID
 * itself is passed through as a non-empty placeholder so
 * {@see \Seviye\Notifications\Dispatch\NotificationDispatcher} doesn't treat
 * it as "no recipient". SMS and WHATSAPP both resolve through Seviye
 * Parents' published {@see ParentContactLookupInterface} (the same phone
 * number - a WhatsApp message needs an MSISDN too) - today the platform's
 * only source of a phone number outside wp_users - so both only ever
 * succeed for veli accounts with a phone on file; every other role (staff,
 * HQ) honestly gets "no recipient", recorded FAILED by the dispatcher
 * rather than a silently-pretended success.
 */
final class WpRecipientResolver implements RecipientResolverInterface
{
    public function __construct(private readonly ParentContactLookupInterface $parentContacts)
    {
    }

    public function resolve(int $userId, NotificationChannel $channel): ?string
    {
        return match ($channel) {
            NotificationChannel::EMAIL => $this->emailFor($userId),
            NotificationChannel::PANEL => (string) $userId,
            NotificationChannel::SMS, NotificationChannel::WHATSAPP => $this->parentContacts->phoneFor($userId),
        };
    }

    private function emailFor(int $userId): ?string
    {
        if (!function_exists('get_userdata')) {
            return null;
        }

        $user = get_userdata($userId);

        if ($user === false || $user->user_email === '') {
            return null;
        }

        return $user->user_email;
    }
}
