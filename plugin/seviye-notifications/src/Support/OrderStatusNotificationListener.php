<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

use Seviye\Core\Events\Event;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;

/**
 * Listens for `commerce.order_cancelled`/`commerce.order_refunded`/
 * `commerce.order_shipped`/`commerce.order_delivered`, all dispatched by
 * Seviye\Commerce\Http\OrderPersistenceHooks or
 * Seviye\Commerce\Http\AdminOrdersRestController - this module never
 * depends on Seviye Commerce's classes or Contracts, only on those
 * documented event names/payload shapes, exactly mirroring
 * OrderPlacedNotificationListener's relationship to `commerce.order_placed`
 * (same self-contained payload: `customer_id`, `order_number`, `total`,
 * `items`, plus `refunded_amount`/`reason` on the refund event and
 * `tracking_number` on the shipped event).
 */
final class OrderStatusNotificationListener
{
    public function __construct(private readonly NotificationDispatcherInterface $dispatcher)
    {
    }

    public function onOrderCancelled(Event $event): void
    {
        $subject = function_exists('__')
            ? __('Siparişiniz İptal Edildi', 'seviye-notifications')
            : 'Siparişiniz İptal Edildi';

        $intro = function_exists('__')
            ? __('Siparişiniz iptal edilmiştir.', 'seviye-notifications')
            : 'Siparişiniz iptal edilmiştir.';

        $this->notify($event, $subject, $intro);
    }

    public function onOrderRefunded(Event $event): void
    {
        $formattedAmount = number_format((float) $event->get('refunded_amount'), 2, ',', '.');

        $subject = function_exists('__')
            ? __('Siparişiniz İçin İade Yapıldı', 'seviye-notifications')
            : 'Siparişiniz İçin İade Yapıldı';

        $intro = function_exists('__')
            /* translators: %s: refunded amount, already formatted with thousand/decimal separators */
            ? sprintf(__('Siparişiniz için %s TRY iade edilmiştir.', 'seviye-notifications'), $formattedAmount)
            : sprintf('Siparişiniz için %s TRY iade edilmiştir.', $formattedAmount);

        $this->notify($event, $subject, $intro);
    }

    /**
     * "Kargoya verildi/teslim edildi" - reuses notify()'s existing
     * `reason` line for the tracking number too (same "empty means omit
     * the line" behavior), rather than adding a second special-cased
     * field, since exactly one of the two ever applies per event.
     */
    public function onOrderShipped(Event $event): void
    {
        $subject = function_exists('__')
            ? __('Siparişiniz Kargoya Verildi', 'seviye-notifications')
            : 'Siparişiniz Kargoya Verildi';

        $intro = function_exists('__')
            ? __('Siparişiniz kargoya verilmiştir.', 'seviye-notifications')
            : 'Siparişiniz kargoya verilmiştir.';

        $trackingNumber = trim((string) $event->get('tracking_number'));
        $noteLabel = function_exists('__')
            ? __('Kargo Takip No', 'seviye-notifications')
            : 'Kargo Takip No';

        $this->notify($event, $subject, $intro, $trackingNumber, $noteLabel);
    }

    public function onOrderDelivered(Event $event): void
    {
        $subject = function_exists('__')
            ? __('Siparişiniz Teslim Edildi', 'seviye-notifications')
            : 'Siparişiniz Teslim Edildi';

        $intro = function_exists('__')
            ? __('Siparişiniz teslim edilmiştir.', 'seviye-notifications')
            : 'Siparişiniz teslim edilmiştir.';

        $this->notify($event, $subject, $intro);
    }

    /**
     * __()'s $text argument must stay a literal string (see
     * PasswordResetNotificationListener's identical docblock note) - this
     * class's own tests run outside a WordPress runtime, where __() is
     * undefined, so every call site above guards it individually.
     *
     * $note/$noteLabel default to the cancel/refund events' own `reason`
     * field - onOrderShipped() passes the tracking number/its own label
     * explicitly instead, since that value doesn't live under `reason`.
     */
    private function notify(
        Event $event,
        string $subject,
        string $intro,
        ?string $note = null,
        ?string $noteLabel = null
    ): void {
        $customerId = (int) $event->get('customer_id');

        if ($customerId <= 0) {
            return;
        }

        $orderNumber = (string) $event->get('order_number');
        $subjectLine = $subject . ' - #' . $orderNumber;
        $lines = [$intro];

        $note ??= trim((string) $event->get('reason'));
        $noteLabel ??= function_exists('__') ? __('Not', 'seviye-notifications') : 'Not';

        if ($note !== '') {
            $lines[] = '';
            $lines[] = $noteLabel . ': ' . $note;
        }

        $this->dispatcher->dispatch(
            $customerId,
            NotificationChannel::EMAIL,
            $event->name(),
            $subjectLine,
            implode("\n", $lines)
        );
    }
}
