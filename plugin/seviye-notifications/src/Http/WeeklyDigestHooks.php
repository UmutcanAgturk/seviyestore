<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Core\Rbac\Role;
use Seviye\Depo\Contracts\PurchaseSuggestionSummaryInterface;
use Seviye\Finance\Contracts\HakedisTotalsInterface;
use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Support\WeeklyDigestBuilder;
use WC_Order;

/**
 * "Haftalık/aylık özet e-postaları (hakediş, stok durumu)" - a WP Cron job
 * (this platform's first) rather than an EventBus reaction: nothing external
 * fires a "week passed" event, so the trigger has to be time itself.
 * Gathers three numbers from three different published Contracts/WooCommerce
 * (never their internal repositories - see docs/ARCHITECTURE.md, "Kural"):
 * Finance's Contracts\HakedisTotalsInterface (platform-wide outstanding
 * hakediş), Depo's Contracts\PurchaseSuggestionSummaryInterface (pending
 * low-stock purchase suggestions), and WooCommerce directly for weekly sales
 * (WC is not a Seviye module - the same "direct wc_get_orders() call in an
 * Http-layer class" precedent Reports\Http\OverviewRestController and
 * Seviye\Commerce\Http\AdminOrdersRestController already establish).
 * WeeklyDigestBuilder turns the three numbers into message text - kept
 * separate so that part stays unit-testable without WordPress.
 *
 * Recipients mirror LowStockNotificationListener's hqUserIds(): every
 * Genel Merkez/Bölge Müdürü user, resolved via Core's own Role enum - stock
 * and hakediş are platform-wide concerns, not scoped to one branch.
 */
final class WeeklyDigestHooks
{
    public const HOOK = 'scp_notifications_weekly_digest';
    private const SCHEDULE = 'scp_weekly';
    private const REPORT_STATUS = 'completed';

    public function __construct(
        private readonly HakedisTotalsInterface $hakedisTotals,
        private readonly PurchaseSuggestionSummaryInterface $purchaseSuggestions,
        private readonly NotificationDispatcherInterface $dispatcher,
        private readonly WeeklyDigestBuilder $builder
    ) {
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'registerWeeklySchedule']);
        add_action(self::HOOK, [$this, 'send']);

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), self::SCHEDULE, self::HOOK);
        }
    }

    /**
     * WP core ships hourly/twicedaily/daily only - no weekly interval - so
     * one has to be added via this filter before wp_schedule_event() can
     * use it.
     *
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public function registerWeeklySchedule(array $schedules): array
    {
        $schedules[self::SCHEDULE] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Haftalık (Seviye)', 'seviye-notifications'),
        ];

        return $schedules;
    }

    public function send(): void
    {
        [$orderCount, $revenue] = $this->weeklySales();

        $message = $this->builder->build(
            $orderCount,
            $revenue,
            $this->hakedisTotals->totalOutstandingBalance(),
            $this->purchaseSuggestions->pendingCount()
        );

        foreach ($this->hqUserIds() as $userId) {
            foreach ([NotificationChannel::EMAIL, NotificationChannel::PANEL] as $channel) {
                $this->dispatcher->dispatch($userId, $channel, self::HOOK, $message->subject, $message->body);
            }
        }
    }

    /**
     * @return array{0: int, 1: float}
     */
    private function weeklySales(): array
    {
        if (!function_exists('wc_get_orders')) {
            return [0, 0.0];
        }

        $orders = wc_get_orders([
            'status' => self::REPORT_STATUS,
            'limit' => -1,
            'date_created' => '>=' . gmdate('Y-m-d', strtotime('-6 days')),
        ]);

        $total = 0.0;

        foreach ($orders as $order) {
            if ($order instanceof WC_Order) {
                $total += (float) $order->get_total();
            }
        }

        return [count($orders), round($total, 2)];
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
}
