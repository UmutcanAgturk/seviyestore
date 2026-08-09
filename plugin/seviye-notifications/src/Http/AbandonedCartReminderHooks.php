<?php

declare(strict_types=1);

namespace Seviye\Notifications\Http;

use Seviye\Notifications\Dispatch\NotificationDispatcherInterface;
use Seviye\Notifications\Domain\NotificationChannel;
use Seviye\Notifications\Support\AbandonedCartReminderBuilder;

/**
 * "Terk edilmiş sepet hatırlatma e-postası" - {@see WeeklyDigestHooks}'ın
 * AYNI "WP Cron, EventBus DEĞİL" gerekçesi (hiçbir şey "sepet terk
 * edildi" olayını platform içinden ateşlemiyor, tetikleyici zamanın
 * kendisi). WooCommerce'in KENDİ "kalıcı sepet" (persistent cart)
 * mekanizmasının üzerine kuruldu - yeni bir sepet-anlık-görüntü tablosu
 * İCAT EDİLMEDİ:
 *
 * 1. WC zaten her sepet değişikliğinde (`woocommerce_add_to_cart`,
 *    `woocommerce_cart_item_removed`, `woocommerce_cart_item_restored`,
 *    `woocommerce_cart_item_set_quantity` - `WC_Cart_Session`'ın kendi
 *    kancaları) giriş yapmış kullanıcının sepetini
 *    `_woocommerce_persistent_cart_{blog_id}` user meta'sına yazıyor VE
 *    başarılı ödeme sonrası `WC_Cart::empty_cart()` bu meta'yı SİLİYOR
 *    (`persistent_cart_destroy()`) - yani gönderim ANINDA bu meta'yı
 *    OKUMAK, dönüşmüş/tamamlanmış bir sepeti otomatik olarak hariç
 *    tutuyor, ayrı bir "sipariş verildi mi" kontrolüne gerek yok.
 * 2. Bu sınıf yalnızca AYNI kancalara takılıp "son sepet etkinliği ne
 *    zamandı" diye kendi `_scp_cart_last_updated_at` user meta'sını
 *    tutuyor (WC bunu saklamıyor) - gönderim zamanlaması için gereken
 *    TEK ek bilgi bu.
 *
 * `_scp_cart_reminder_sent_at`, AYNI sepet durumu için tekrar tekrar
 * hatırlatma gönderilmesini önlüyor - yalnızca `_scp_cart_last_updated_at`
 * bir SONRAKİ sepet etkinliğiyle ilerlediğinde (kullanıcı sepete
 * dokunduğunda) bir hatırlatma yeniden uygun hale geliyor.
 */
final class AbandonedCartReminderHooks
{
    public const HOOK = 'scp_notifications_abandoned_cart_reminder';
    private const CART_ACTIVITY_META_KEY = '_scp_cart_last_updated_at';
    private const REMINDER_SENT_META_KEY = '_scp_cart_reminder_sent_at';
    private const REMINDER_DELAY_HOURS = 24;

    public function __construct(
        private readonly NotificationDispatcherInterface $dispatcher,
        private readonly AbandonedCartReminderBuilder $builder
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_add_to_cart', [$this, 'recordCartActivity']);
        add_action('woocommerce_cart_item_removed', [$this, 'recordCartActivity']);
        add_action('woocommerce_cart_item_restored', [$this, 'recordCartActivity']);
        add_action('woocommerce_cart_item_set_quantity', [$this, 'recordCartActivity']);

        add_action(self::HOOK, [$this, 'send']);

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'daily', self::HOOK);
        }
    }

    public function recordCartActivity(): void
    {
        $userId = get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        update_user_meta($userId, self::CART_ACTIVITY_META_KEY, time());
    }

    public function send(): void
    {
        foreach ($this->usersWithTrackedCartActivity() as $userId) {
            $this->maybeSendReminder($userId);
        }
    }

    /**
     * @return list<int>
     */
    private function usersWithTrackedCartActivity(): array
    {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users([
            'meta_key' => self::CART_ACTIVITY_META_KEY,
            'meta_compare' => 'EXISTS',
            'fields' => 'ID',
        ]);

        return array_map('intval', $users);
    }

    private function maybeSendReminder(int $userId): void
    {
        $lastUpdated = (int) get_user_meta($userId, self::CART_ACTIVITY_META_KEY, true);

        if ($lastUpdated <= 0 || (time() - $lastUpdated) < self::REMINDER_DELAY_HOURS * HOUR_IN_SECONDS) {
            return;
        }

        $reminderSentAt = (int) get_user_meta($userId, self::REMINDER_SENT_META_KEY, true);

        if ($reminderSentAt >= $lastUpdated) {
            return;
        }

        $items = $this->cartItems($userId);

        if ($items === []) {
            return;
        }

        $cartUrl = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/sepet');
        $message = $this->builder->build($items, $cartUrl);

        $this->dispatcher->dispatch($userId, NotificationChannel::EMAIL, self::HOOK, $message->subject, $message->body);

        update_user_meta($userId, self::REMINDER_SENT_META_KEY, time());
    }

    /**
     * @return list<array{name: string, quantity: int}>
     */
    private function cartItems(int $userId): array
    {
        if (!function_exists('get_current_blog_id')) {
            return [];
        }

        $persistedCart = get_user_meta($userId, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);

        if (!is_array($persistedCart) || !isset($persistedCart['cart']) || !is_array($persistedCart['cart'])) {
            return [];
        }

        $items = [];

        foreach ($persistedCart['cart'] as $line) {
            if (!is_array($line) || !isset($line['product_id'], $line['quantity'])) {
                continue;
            }

            $product = function_exists('wc_get_product') ? wc_get_product((int) $line['product_id']) : false;

            if (!$product) {
                continue;
            }

            $items[] = ['name' => $product->get_name(), 'quantity' => (int) $line['quantity']];
        }

        return $items;
    }
}
