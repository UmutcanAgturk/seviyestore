<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

use Seviye\Core\Events\Event;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;

/**
 * Listens for `commerce.stock_subscription_fulfilled`, dispatched by
 * Seviye\Commerce\Http\BackInStockNotificationHooks - this module never
 * depends on Seviye Commerce's classes or Contracts, only on that
 * documented event name/payload shape (`user_id`, `product_name`), exactly
 * mirroring OrderPlacedNotificationListener's relationship to Commerce.
 *
 * Unlike LowStockNotificationListener (one platform-wide event, every HQ
 * user notified), Commerce dispatches ONE of these events PER SUBSCRIBER -
 * `user_id` is always that single subscriber, so this listener sends
 * exactly one PANEL + one EMAIL notification per event, the same
 * single-recipient shape as OrderPlacedNotificationListener.
 */
final class BackInStockNotificationListener
{
    public function __construct(private readonly NotificationDispatcherInterface $dispatcher)
    {
    }

    public function onStockSubscriptionFulfilled(Event $event): void
    {
        $userId = (int) $event->get('user_id');

        if ($userId <= 0) {
            return;
        }

        $productName = (string) $event->get('product_name');
        $subject = $this->subjectFor($productName);
        $body = $this->bodyFor($productName);

        $this->dispatcher->dispatch($userId, NotificationChannel::PANEL, $event->name(), $subject, $body);
        $this->dispatcher->dispatch($userId, NotificationChannel::EMAIL, $event->name(), $subject, $body);
    }

    /**
     * __()'s $text argument must stay a literal string (see
     * PasswordResetNotificationListener's identical docblock note) - this
     * class's own tests run outside a WordPress runtime, where __() is
     * undefined, so the guard falls back to the raw Turkish string.
     */
    private function subjectFor(string $productName): string
    {
        return function_exists('__')
            /* translators: %s: product name */
            ? sprintf(__('Stoğa Geldi: %s', 'seviye-notifications'), $productName)
            : 'Stoğa Geldi: ' . $productName;
    }

    private function bodyFor(string $productName): string
    {
        $template = function_exists('__')
            /* translators: %s: product name */
            ? __('Haber vermenizi istediğiniz "%s" ürünü tekrar stoğa girdi.', 'seviye-notifications')
            : 'Haber vermenizi istediğiniz "%s" ürünü tekrar stoğa girdi.';

        return sprintf($template, $productName);
    }
}
