<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Rbac\ProductCapability;
use Seviye\Commerce\Support\TaxRateGateway;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/tax-rates - "Ürün ürün vergilendirme" (bkz.
 * docs/ARCHITECTURE.md). HQ (scp_manage_tax_rates) defines/edits/removes
 * named tax rate classes here; ProductsRestController's `tax_class` field
 * then lets anyone who can already edit a product (scp_manage_products)
 * PICK one of these for it - see TaxRateGateway's own docblock for why
 * that split exists and how this wraps WooCommerce's OWN tax engine
 * instead of a new Seviye table.
 *
 * Every write action (POST/PUT/DELETE) checks
 * {@see TaxRateGateway::isSupported()} first and returns 501 rather than a
 * raw fatal if the underlying WC_Tax methods this wraps are missing on the
 * site's WooCommerce version - see TaxRateGateway's docblock.
 */
final class TaxRatesRestController extends AbstractRestController
{
    public function __construct(private readonly TaxRateGateway $gateway)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/tax-rates', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canView'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_TAX_RATES->value),
                'args' => [
                    'name' => ['required' => true, 'type' => 'string'],
                    'percent' => ['required' => true, 'type' => 'number'],
                ],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/tax-rates/(?P<slug>[^/]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_TAX_RATES->value),
                'args' => [
                    'percent' => ['required' => true, 'type' => 'number'],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'destroy'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_TAX_RATES->value),
            ],
        ]);
    }

    /**
     * Read access is wider than write: a Şube Müdürü/Muhasebe/Depo/Sistem
     * viewing or editing a product still needs this list for the
     * `tax_class` dropdown, even though only MANAGE_TAX_RATES holders may
     * change what's in it.
     */
    public function canView(): bool
    {
        return current_user_can(ProductCapability::MANAGE_PRODUCTS->value)
            || current_user_can(ProductCapability::VIEW_PRODUCTS->value)
            || current_user_can(ProductCapability::MANAGE_TAX_RATES->value);
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response($this->gateway->list());
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->gateway->isSupported()) {
            return $this->unsupportedResponse();
        }

        $name = trim((string) $request->get_param('name'));
        $percent = (float) $request->get_param('percent');

        if ($name === '' || $percent < 0) {
            return new WP_REST_Response(
                ['message' => __('Geçerli bir isim ve oran gerekli.', 'seviye-commerce')],
                422
            );
        }

        $created = $this->gateway->create($name, $percent);

        if ($created === null) {
            return new WP_REST_Response(
                ['message' => __('Bu isimde bir vergi oranı zaten var.', 'seviye-commerce')],
                422
            );
        }

        return new WP_REST_Response($created, 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->gateway->isSupported()) {
            return $this->unsupportedResponse();
        }

        $percent = (float) $request->get_param('percent');

        if ($percent < 0) {
            return new WP_REST_Response(['message' => __('Geçerli bir oran gerekli.', 'seviye-commerce')], 422);
        }

        $updated = $this->gateway->update((string) $request->get_param('slug'), $percent);

        if ($updated === null) {
            return new WP_REST_Response(['message' => __('Vergi oranı bulunamadı.', 'seviye-commerce')], 404);
        }

        return new WP_REST_Response($updated);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->gateway->isSupported()) {
            return $this->unsupportedResponse();
        }

        if (!$this->gateway->delete((string) $request->get_param('slug'))) {
            return new WP_REST_Response(
                [
                    'message' => __(
                        'Standart vergi oranı ya da hâlâ bir üründe kullanılan bir oran silinemez.',
                        'seviye-commerce'
                    ),
                ],
                422
            );
        }

        return new WP_REST_Response(['success' => true]);
    }

    private function unsupportedResponse(): WP_REST_Response
    {
        return new WP_REST_Response(
            ['message' => __('Bu WooCommerce sürümü vergi oranı yönetimini desteklemiyor.', 'seviye-commerce')],
            501
        );
    }
}
