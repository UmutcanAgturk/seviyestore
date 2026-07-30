<?php

declare(strict_types=1);

namespace Seviye\Notifications\Recipient;

use Seviye\Notifications\Domain\NotificationChannel;

/**
 * Resolves the channel-specific "address" a
 * {@see \Seviye\Notifications\Channel\ChannelInterface} needs to deliver to
 * (an e-mail address, a phone number...) for a given WordPress user ID. A
 * null return means this channel cannot currently reach this user - not an
 * error, just nothing to deliver to yet (e.g. no phone number on file).
 */
interface RecipientResolverInterface
{
    public function resolve(int $userId, NotificationChannel $channel): ?string;
}
