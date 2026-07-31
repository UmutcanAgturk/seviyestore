<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

use Seviye\Core\Events\Event;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;

/**
 * Listens for `commerce.order_placed`, dispatched by
 * Seviye\Commerce\Http\OrderPersistenceHooks::persistOrderLineItems() -
 * this module never depends on Seviye Commerce's classes or Contracts,
 * only on that documented event name/payload shape (`customer_id`,
 * `order_number`, `total`, `items`), exactly mirroring
 * PasswordResetNotificationListener's relationship to Seviye Security.
 * "Veliler sipariş verdiğinde otomatik olarak velilerin mailine mail
 * gidecek bir sistem" - the recipient is always `customer_id` (the veli who
 * placed the order), resolved to an e-mail address the same way every
 * other EMAIL notification on this platform is (get_userdata() via
 * Recipient\WpRecipientResolver - no Commerce-specific lookup needed).
 */
final class OrderPlacedNotificationListener
{
    public function __construct(private readonly NotificationDispatcherInterface $dispatcher)
    {
    }

    public function onOrderPlaced(Event $event): void
    {
        $customerId = (int) $event->get('customer_id');

        if ($customerId <= 0) {
            return;
        }

        $this->dispatcher->dispatch(
            $customerId,
            NotificationChannel::EMAIL,
            $event->name(),
            $this->subjectFor((string) $event->get('order_number')),
            $this->bodyFor($event)
        );
    }

    /**
     * __()'s $text argument must stay a literal string (see
     * PasswordResetNotificationListener's identical docblock note) - this
     * class's own tests run outside a WordPress runtime, where __() is
     * undefined, so the guard falls back to the raw Turkish string.
     */
    private function subjectFor(string $orderNumber): string
    {
        return function_exists('__')
            /* translators: %s: order number */
            ? sprintf(__('Siparişiniz Alındı - #%s', 'seviye-notifications'), $orderNumber)
            : 'Siparişiniz Alındı - #' . $orderNumber;
    }

    private function bodyFor(Event $event): string
    {
        $orderNumber = (string) $event->get('order_number');
        $total = (float) $event->get('total');
        $items = $event->get('items');

        $intro = function_exists('__')
            ? sprintf(
                /* translators: %s: order number */
                __('Siparişiniz alınmıştır: #%s', 'seviye-notifications'),
                $orderNumber
            )
            : 'Siparişiniz alınmıştır: #' . $orderNumber;

        $lines = [$intro, ''];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- %s x%d: %s TRY',
                    (string) ($item['name'] ?? ''),
                    (int) ($item['quantity'] ?? 1),
                    number_format((float) ($item['line_total'] ?? 0), 2, ',', '.')
                );
            }

            $lines[] = '';
        }

        $totalLabel = function_exists('__') ? __('Toplam', 'seviye-notifications') : 'Toplam';
        $lines[] = $totalLabel . ': ' . number_format($total, 2, ',', '.') . ' TRY';

        return implode("\n", $lines);
    }
}
