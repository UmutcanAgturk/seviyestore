<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Repository\StockSubscriptionRepositoryInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/stock-subscriptions - "stok gelince haber ver". Every
 * route is scoped to the CURRENT user's own subscriptions (get_current_user_id()),
 * never an id the client supplies - the same "server resolves scope" rule
 * every other *\/mine-shaped endpoint on this platform follows (see
 * OrdersRestController's docblock). Gated by `scp_view_own_children` -
 * the same veli-only capability ProductReviewGate uses for "who may act on
 * a product as a customer", since a subscription is a customer action, not
 * a staff one.
 */
final class StockSubscriptionsRestController extends AbstractRestController
{
    public function __construct(private readonly StockSubscriptionRepositoryInterface $repository)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/stock-subscriptions', [
            'methods' => 'POST',
            'callback' => [$this, 'subscribe'],
            'permission_callback' => [$this, 'isVeli'],
            'args' => [
                'product_id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/stock-subscriptions/(?P<product_id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'isVeli'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'unsubscribe'],
                'permission_callback' => [$this, 'isVeli'],
            ],
        ]);
    }

    public function isVeli(): bool
    {
        return is_user_logged_in() && current_user_can('scp_view_own_children');
    }

    public function subscribe(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('product_id');

        if ($productId <= 0 || (function_exists('get_post_type') && get_post_type($productId) !== 'product')) {
            return new WP_REST_Response(['message' => __('Ürün bulunamadı.', 'seviye-commerce')], 404);
        }

        $this->repository->subscribe($productId, get_current_user_id());

        return new WP_REST_Response(['subscribed' => true], 201);
    }

    public function unsubscribe(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('product_id');

        $this->repository->unsubscribe($productId, get_current_user_id());

        return new WP_REST_Response(['subscribed' => false]);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('product_id');

        return new WP_REST_Response([
            'subscribed' => $this->repository->isSubscribed($productId, get_current_user_id()),
        ]);
    }
}
