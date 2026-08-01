<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Rbac\CouponCapability;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WC_Coupon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/coupons - "OKUL2026" gibi zaman sınırlı, tek
 * kullanımlık promosyon kodları. Distinct from Seviye Pricing's structural
 * branch/student price rules: a coupon is customer-entered at checkout
 * (WooCommerce's own native `[woocommerce_cart]` "Kupon Kodu Uygula" form,
 * no theme work needed there), not automatically resolved per line item.
 *
 * A thin wrapper around WooCommerce's own WC_Coupon (shop_coupon post
 * type) - coupons stay entirely WooCommerce's, same "Kural" as Products/
 * Orders. See docs/ARCHITECTURE.md.
 */
final class CouponsRestController extends AbstractRestController
{
    private const VALID_DISCOUNT_TYPES = ['percent', 'fixed_cart', 'fixed_product'];

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/coupons', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(CouponCapability::MANAGE_COUPONS->value),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(CouponCapability::MANAGE_COUPONS->value),
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/coupons/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(CouponCapability::MANAGE_COUPONS->value),
                'args' => $this->writableArgs(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'destroy'],
                'permission_callback' => $this->requireCapability(CouponCapability::MANAGE_COUPONS->value),
            ],
        ]);
    }

    public function index(): WP_REST_Response
    {
        $posts = get_posts([
            'post_type' => 'shop_coupon',
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $coupons = array_map(
            fn (\WP_Post $post): array => $this->serialize(new WC_Coupon($post->ID)),
            $posts
        );

        return new WP_REST_Response($coupons);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $code = $this->normalizeCode((string) $request->get_param('code'));
        $discountType = (string) $request->get_param('discount_type');
        $amount = (float) $request->get_param('amount');

        $error = $this->validate($code, $discountType, $amount);

        if ($error !== null) {
            return new WP_REST_Response(['message' => $error], 422);
        }

        if (wc_get_coupon_id_by_code($code) > 0) {
            return new WP_REST_Response(['message' => __('Bu kupon kodu zaten kullanılıyor.', 'seviye-commerce')], 422);
        }

        $coupon = new WC_Coupon();
        $this->applyWritableFields($coupon, $code, $discountType, $amount, $request);
        $coupon->save();

        return new WP_REST_Response($this->serialize($coupon), 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $couponId = (int) $request->get_param('id');
        $coupon = new WC_Coupon($couponId);

        if ($coupon->get_id() !== $couponId) {
            return new WP_REST_Response(['message' => __('Kupon bulunamadı.', 'seviye-commerce')], 404);
        }

        $code = $this->normalizeCode((string) $request->get_param('code'));
        $discountType = (string) $request->get_param('discount_type');
        $amount = (float) $request->get_param('amount');

        $error = $this->validate($code, $discountType, $amount);

        if ($error !== null) {
            return new WP_REST_Response(['message' => $error], 422);
        }

        $existingId = wc_get_coupon_id_by_code($code);

        if ($existingId > 0 && $existingId !== $couponId) {
            return new WP_REST_Response(['message' => __('Bu kupon kodu zaten kullanılıyor.', 'seviye-commerce')], 422);
        }

        $this->applyWritableFields($coupon, $code, $discountType, $amount, $request);
        $coupon->save();

        return new WP_REST_Response($this->serialize($coupon));
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $couponId = (int) $request->get_param('id');

        if (get_post_type($couponId) !== 'shop_coupon') {
            return new WP_REST_Response(['message' => __('Kupon bulunamadı.', 'seviye-commerce')], 404);
        }

        wp_delete_post($couponId, true);

        return new WP_REST_Response(['success' => true]);
    }

    private function validate(string $code, string $discountType, float $amount): ?string
    {
        if ($code === '' || !in_array($discountType, self::VALID_DISCOUNT_TYPES, true) || $amount <= 0) {
            return __('Geçerli bir kod, indirim türü ve tutar gerekli.', 'seviye-commerce');
        }

        return null;
    }

    private function normalizeCode(string $raw): string
    {
        return strtoupper(trim($raw));
    }

    private function applyWritableFields(
        WC_Coupon $coupon,
        string $code,
        string $discountType,
        float $amount,
        WP_REST_Request $request
    ): void {
        $coupon->set_code($code);
        $coupon->set_discount_type($discountType);
        $coupon->set_amount((string) $amount);
        $coupon->set_description((string) ($request->get_param('description') ?? ''));

        $usageLimit = $request->get_param('usage_limit');
        $coupon->set_usage_limit($usageLimit !== null && $usageLimit !== '' ? (int) $usageLimit : 0);

        $expiryDate = trim((string) ($request->get_param('expiry_date') ?? ''));
        $coupon->set_date_expires($expiryDate !== '' ? $expiryDate : null);
        $coupon->set_status('publish');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WC_Coupon $coupon): array
    {
        $expires = $coupon->get_date_expires();

        return [
            'id' => $coupon->get_id(),
            'code' => $coupon->get_code(),
            'discount_type' => $coupon->get_discount_type(),
            'amount' => (float) $coupon->get_amount(),
            'description' => $coupon->get_description(),
            'usage_limit' => $coupon->get_usage_limit() ?: null,
            'usage_count' => $coupon->get_usage_count(),
            'expiry_date' => $expires !== null ? $expires->date('Y-m-d') : null,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function writableArgs(): array
    {
        return [
            'code' => ['required' => true, 'type' => 'string'],
            'discount_type' => ['required' => true, 'type' => 'string'],
            'amount' => ['required' => true, 'type' => 'number'],
            'description' => ['required' => false, 'type' => 'string'],
            'usage_limit' => ['required' => false, 'type' => 'integer'],
            'expiry_date' => ['required' => false, 'type' => 'string'],
        ];
    }
}
