<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Support\ProductViewerTracker;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/products/{id}/viewing - "Bu ürünü şu an X kişi
 * görüntülüyor" sosyal kanıt sayacı için tek uç nokta. Self-service,
 * authenticated only (`is_user_logged_in()`, no RBAC capability) -
 * CustomerAddressRestController/OrdersRestController'ın AYNI "sunucu
 * kendi kimliğini çözer" ilkesi; herhangi bir giriş yapmış kullanıcı
 * yalnızca KENDİ görüntülemesini kaydedebilir (`get_current_user_id()`,
 * request body'den bir kullanıcı id'si ALINMIYOR).
 */
final class ProductViewingRestController extends AbstractRestController
{
    public function __construct(private readonly ProductViewerTracker $tracker)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/products/(?P<id>\d+)/viewing', [
            'methods' => 'POST',
            'callback' => [$this, 'record'],
            'permission_callback' => [$this, 'isLoggedIn'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function record(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('id');
        $viewerCount = $this->tracker->recordView($productId, get_current_user_id());

        return new WP_REST_Response(['viewer_count' => $viewerCount]);
    }
}
