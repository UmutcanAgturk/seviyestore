<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Rbac\ProductCapability;
use Seviye\Commerce\Support\ShopShowcase;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/shop-showcase - read/write the "Mağaza Vitrini" (bkz.
 * Support\ShopShowcase). Genel Merkez/Bölge Müdürü only
 * (ProductCapability::MANAGE_SHOP_SHOWCASE), same GET/PUT-whole-setting
 * shape as SizeGuideRestController/BrandingRestController.
 * `image_attachment_id`'nin gerçek bir görsel olup olmadığı
 * BrandingRestController'la AYNI şekilde `wp_attachment_is_image()` ile
 * doğrulanıyor, 0 açıkça görseli temizliyor.
 */
final class ShopShowcaseRestController extends AbstractRestController
{
    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/shop-showcase', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_SHOP_SHOWCASE->value),
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_SHOP_SHOWCASE->value),
                'args' => [
                    'heading' => ['required' => false, 'type' => 'string'],
                    'subheading' => ['required' => false, 'type' => 'string'],
                    'image_attachment_id' => ['required' => false, 'type' => 'integer'],
                    'seasonal_theme' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function show(): WP_REST_Response
    {
        return new WP_REST_Response($this->serialize($this->currentShowcase()));
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $imageAttachmentId = (int) $request->get_param('image_attachment_id');

        if ($imageAttachmentId !== 0 && !wp_attachment_is_image($imageAttachmentId)) {
            return new WP_REST_Response(['message' => __('Geçersiz görsel.', 'seviye-commerce')], 422);
        }

        $seasonalTheme = trim((string) $request->get_param('seasonal_theme'));

        if ($seasonalTheme !== '' && !in_array($seasonalTheme, ShopShowcase::SEASONAL_THEMES, true)) {
            return new WP_REST_Response(['message' => __('Geçersiz sezonluk tema.', 'seviye-commerce')], 422);
        }

        $showcase = new ShopShowcase(
            trim((string) $request->get_param('heading')),
            trim((string) $request->get_param('subheading')),
            $imageAttachmentId > 0 ? $imageAttachmentId : null,
            $seasonalTheme
        );

        $this->settings->set(ShopShowcase::SETTING_KEY, ShopShowcase::serialize($showcase));

        return new WP_REST_Response($this->serialize($showcase));
    }

    /**
     * @return array{heading: string, subheading: string, image_attachment_id: ?int, image_url: ?string, seasonal_theme: string}
     */
    private function serialize(ShopShowcase $showcase): array
    {
        return [
            'heading' => $showcase->heading,
            'subheading' => $showcase->subheading,
            'image_attachment_id' => $showcase->imageAttachmentId,
            'image_url' => $showcase->imageAttachmentId
                ? (wp_get_attachment_image_url($showcase->imageAttachmentId, 'large') ?: null)
                : null,
            'seasonal_theme' => $showcase->seasonalTheme,
        ];
    }

    private function currentShowcase(): ShopShowcase
    {
        return ShopShowcase::parse($this->settings->get(ShopShowcase::SETTING_KEY));
    }
}
