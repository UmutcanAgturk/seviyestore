<?php

declare(strict_types=1);

namespace Seviye\Notifications\Support;

/**
 * Builds the "haftalık özet" (weekly digest) email/panel message content
 * from already-gathered numbers - kept free of any WordPress/WooCommerce
 * call, EventBusInterface, or a Contracts dependency, so it is fully
 * unit-testable without a database fake. Http\WeeklyDigestHooks gathers
 * the numbers (from Finance's Contracts\HakedisTotalsInterface, Depo's
 * Contracts\PurchaseSuggestionSummaryInterface, and WooCommerce directly)
 * and hands them here - the same "Support classes stay pure, Http classes
 * touch the platform" split every other module in this codebase follows
 * (see Seviye\Reports\Support\SalesReportBuilder's docblock).
 *
 * __()'s $text argument must stay a literal string (WordPress' own i18n
 * tooling scans source for it verbatim; a shared wrapper taking a $text
 * argument, even behind a function_exists() guard, breaks that) - see
 * Seviye\Notifications\Support\PasswordResetNotificationListener's
 * identical docblock note. Each line below therefore repeats its own
 * function_exists('__') guard and its own literal string rather than
 * calling a shared translate() helper.
 */
final class WeeklyDigestBuilder
{
    public function build(
        int $weeklyOrderCount,
        float $weeklyRevenue,
        float $outstandingHakedisBalance,
        int $pendingPurchaseSuggestionCount
    ): WeeklyDigestMessage {
        $subject = function_exists('__')
            ? __('Haftalık Özet - Seviye Commerce Platform', 'seviye-notifications')
            : 'Haftalık Özet - Seviye Commerce Platform';

        $orderCountTemplate = function_exists('__')
            /* translators: %d: order count over the last 7 days */
            ? __('Sipariş sayısı (son 7 gün): %d', 'seviye-notifications')
            : 'Sipariş sayısı (son 7 gün): %d';

        $revenueTemplate = function_exists('__')
            /* translators: %s: revenue over the last 7 days, formatted as TRY */
            ? __('Ciro (son 7 gün): %s TRY', 'seviye-notifications')
            : 'Ciro (son 7 gün): %s TRY';

        $balanceTemplate = function_exists('__')
            /* translators: %s: platform-wide outstanding hakediş balance, formatted as TRY */
            ? __('Toplam bekleyen hakediş bakiyesi: %s TRY', 'seviye-notifications')
            : 'Toplam bekleyen hakediş bakiyesi: %s TRY';

        $suggestionsTemplate = function_exists('__')
            /* translators: %d: pending low-stock purchase suggestion count */
            ? __('Bekleyen düşük stok satın alma önerisi: %d', 'seviye-notifications')
            : 'Bekleyen düşük stok satın alma önerisi: %d';

        $lines = [
            sprintf($orderCountTemplate, $weeklyOrderCount),
            sprintf($revenueTemplate, number_format($weeklyRevenue, 2, ',', '.')),
            sprintf($balanceTemplate, number_format($outstandingHakedisBalance, 2, ',', '.')),
            sprintf($suggestionsTemplate, $pendingPurchaseSuggestionCount),
        ];

        return new WeeklyDigestMessage($subject, implode("\n", $lines));
    }
}
