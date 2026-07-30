<?php

declare(strict_types=1);

namespace Seviye\Notifications\Tests\Fakes;

use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Recipient\RecipientResolverInterface;

final class FakeRecipientResolver implements RecipientResolverInterface
{
    /** @var array<string, string> keyed by "{userId}:{channel}" */
    public array $addresses = [];

    public function resolve(int $userId, NotificationChannel $channel): ?string
    {
        return $this->addresses[$userId . ':' . $channel->value] ?? null;
    }
}
