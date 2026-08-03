<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Rbac\ProductCapability;
use WP_Error;

/**
 * "Ürün inceleme/puanlama" - WooCommerce'in yerli değerlendirme sistemi
 * (yorum + yıldız puanı, product post type üzerinde sıradan bir WP comment)
 * bu platformda hiç açılmamıştı. Yeniden bir sistem kurmak yerine WC'nin
 * kendi mekanizması AÇILIR, tek eklenen kısıtlama: yalnızca gerçekten
 * SATIN ALMIŞ bir veli değerlendirme bırakabilir - WooCommerce'in kendi
 * "yalnızca doğrulanmış alıcı" ayarına (woocommerce_review_rating_verification_required)
 * güvenmek yerine burada `pre_comment_approved` üzerinden açıkça
 * reddedilir, çünkü o ayar yalnızca "doğrulanmış" rozetini kontrol eder,
 * gönderimi ENGELLEMEZ - platformun isteği ("yalnızca ... puanlama/yorum
 * hakkı") tam bir engelleme, ürün sayfasının zaten login-gated olmasından
 * (inc/access-gate.php) bağımsız bir ikinci katman.
 */
final class ProductReviewGate
{
    public function register(): void
    {
        $this->ensureReviewsEnabled();

        add_filter('pre_comment_approved', [$this, 'restrictToVerifiedBuyers'], 10, 2);
    }

    /**
     * Self-heal on every load - same "don't rely on a one-time activation
     * hook that may not have run" reasoning as
     * theme/seviye-storefront/inc/woocommerce.php's scp_ensure_shop_page_exists().
     * `update_option()` is a no-op write when the value already matches, so
     * this costs nothing once the options are set.
     */
    private function ensureReviewsEnabled(): void
    {
        update_option('woocommerce_enable_reviews', 'yes');
        update_option('woocommerce_enable_review_rating', 'yes');
        update_option('woocommerce_review_rating_verification_label', 'yes');
    }

    /**
     * @param int|string|WP_Error $approved
     * @param array<string, mixed> $commentData
     * @return int|string|WP_Error
     */
    public function restrictToVerifiedBuyers(int|string|WP_Error $approved, array $commentData): int|string|WP_Error
    {
        if ($approved instanceof WP_Error) {
            return $approved;
        }

        $postId = (int) ($commentData['comment_post_ID'] ?? 0);

        if (get_post_type($postId) !== 'product') {
            return $approved;
        }

        $userId = (int) ($commentData['user_id'] ?? get_current_user_id());

        // Ürün yönetimi yapan personel (Ürünler paneli), kendi ürünlerini
        // test amaçlı yorumlayabilmeli - ProductVisibilityHooks'un
        // currentUserBypasses() ile aynı ilke.
        if ($userId > 0 && current_user_can(ProductCapability::MANAGE_PRODUCTS->value)) {
            return $approved;
        }

        if ($userId <= 0 || !current_user_can('scp_view_own_children')) {
            return new WP_Error(
                'scp_review_not_allowed',
                __('Ürün değerlendirmesi yalnızca veli hesapları içindir.', 'seviye-commerce')
            );
        }

        if (!function_exists('wc_customer_bought_product') || !wc_customer_bought_product('', $userId, $postId)) {
            return new WP_Error(
                'scp_review_not_purchased',
                __('Bu ürünü değerlendirebilmek için önce satın almış olmanız gerekir.', 'seviye-commerce')
            );
        }

        return $approved;
    }
}
