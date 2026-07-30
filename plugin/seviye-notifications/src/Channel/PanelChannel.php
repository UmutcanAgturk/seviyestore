<?php

declare(strict_types=1);

namespace Seviye\Notifications\Channel;

/**
 * The scp_notifications row itself IS the delivery for an in-app
 * notification - there is no external transport to call, so this always
 * reports success. {@see \Seviye\Notifications\Dispatch\NotificationDispatcher}
 * still routes PANEL notifications through this (rather than special-casing
 * the channel) so every channel goes through the exact same
 * record→send→mark-sent/failed flow, with no exception for PANEL.
 */
final class PanelChannel implements ChannelInterface
{
    public function send(string $recipient, string $subject, string $body): bool
    {
        return true;
    }
}
