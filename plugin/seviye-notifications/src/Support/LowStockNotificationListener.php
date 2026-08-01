<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

use Seviye\Core\Events\Event;
use Seviye\Core\Rbac\Role;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;

/**
 * Listens for `commerce.product_low_stock`, dispatched by
 * Seviye\Commerce\Http\LowStockNotificationHooks - this module never
 * depends on Seviye Commerce's classes or Contracts, only on that
 * documented event name/payload shape (`product_name`, `stock_quantity`),
 * exactly mirroring OrderPlacedNotificationListener's relationship to
 * Commerce.
 *
 * Recipients are every Genel Merkez/Bölge Müdürü user - stock is
 * platform-wide (a single shared WC_Product, not owned by one branch), so
 * unlike a branch-scoped broadcast this always reaches the whole HQ tier.
 * Resolved via get_users() against Core's own Role enum - a native WP role
 * query, no cross-module Contract needed, since roles are Core's, not
 * Students'/Branches'.
 */
final class LowStockNotificationListener
{
    public function __construct(private readonly NotificationDispatcherInterface $dispatcher)
    {
    }

    public function onLowStock(Event $event): void
    {
        $productName = (string) $event->get('product_name');
        $stockQuantity = (int) $event->get('stock_quantity');
        $subject = $this->subjectFor($productName);
        $body = $this->bodyFor($productName, $stockQuantity);

        foreach ($this->hqUserIds() as $userId) {
            $this->dispatcher->dispatch($userId, NotificationChannel::PANEL, $event->name(), $subject, $body);
            $this->dispatcher->dispatch($userId, NotificationChannel::EMAIL, $event->name(), $subject, $body);
        }
    }

    /**
     * @return list<int>
     */
    private function hqUserIds(): array
    {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users([
            'role__in' => [Role::GENEL_MERKEZ->value, Role::BOLGE_MUDURU->value],
            'fields' => 'ID',
        ]);

        return array_map('intval', $users);
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
            ? sprintf(__('Düşük Stok Uyarısı: %s', 'seviye-notifications'), $productName)
            : 'Düşük Stok Uyarısı: ' . $productName;
    }

    private function bodyFor(string $productName, int $stockQuantity): string
    {
        return function_exists('__')
            ? sprintf(
                /* translators: 1: product name, 2: remaining stock quantity */
                __('"%1$s" ürününün stoğu azaldı - kalan adet: %2$d.', 'seviye-notifications'),
                $productName,
                $stockQuantity
            )
            : sprintf('"%s" ürününün stoğu azaldı - kalan adet: %d.', $productName, $stockQuantity);
    }
}
