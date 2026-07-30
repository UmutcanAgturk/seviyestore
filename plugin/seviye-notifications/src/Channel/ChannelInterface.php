<?php

declare(strict_types=1);

namespace Seviye\Notifications\Channel;

/**
 * One delivery transport (email, SMS, in-app panel). Implementations never
 * throw - a transport-level failure is reported through the boolean return
 * value, so {@see \Seviye\Notifications\Dispatch\NotificationDispatcher} can
 * record the outcome without a misbehaving channel aborting the EventBus
 * listener that triggered it (an EventBus listener throwing would propagate
 * back through {@see \Seviye\Core\Events\EventBus::dispatch()} into whatever
 * WordPress/WooCommerce code fired the original event).
 */
interface ChannelInterface
{
    public function send(string $recipient, string $subject, string $body): bool;
}
