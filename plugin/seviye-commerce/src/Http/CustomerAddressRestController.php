<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WC_Customer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/customer/me/addresses - self-service, authenticated
 * only (is_user_logged_in(), no RBAC capability - the same "server resolves
 * scope" /mine pattern every self-service endpoint on this platform
 * follows, see OrdersRestController). "Velinin profilinde Gönderim adresi
 * ve fatura adresi bölümü de olsun."
 *
 * Reads/writes WooCommerce's own WC_Customer billing_ and shipping_ user
 * meta directly - NOT a new Seviye-owned address table - the same "stay
 * entirely WooCommerce's own" rule already applied to products/orders (see
 * docs/ARCHITECTURE.md, "Kural"). Keeping addresses here means
 * WooCommerce's own checkout auto-fills from whatever the veli already
 * saved in Profilim, and vice versa - a second, disconnected address store
 * would only create two places that could drift out of sync.
 */
final class CustomerAddressRestController extends AbstractRestController
{
    /**
     * WC_Customer exposes get_{billing|shipping}_{field}()/
     * set_{billing|shipping}_{field}() for every one of these - iterating
     * the list with a dynamic method name avoids repeating the same
     * eight-field get/set pair four times (billing get, billing set,
     * shipping get, shipping set) verbatim.
     */
    private const FIELDS = [
        'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country',
    ];

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/customer/me/addresses', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'isLoggedIn'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'isLoggedIn'],
            ],
        ]);
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function show(): WP_REST_Response
    {
        $customer = new WC_Customer(get_current_user_id());

        return new WP_REST_Response([
            'billing' => $this->getAddress($customer, 'billing', true),
            'shipping' => $this->getAddress($customer, 'shipping', false),
        ]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $customer = new WC_Customer(get_current_user_id());

        $this->setAddress($customer, 'billing', (array) ($request->get_param('billing') ?? []), true);
        $this->setAddress($customer, 'shipping', (array) ($request->get_param('shipping') ?? []), false);
        $customer->save();

        return $this->show();
    }

    /**
     * @return array<string, string>
     */
    private function getAddress(WC_Customer $customer, string $type, bool $withPhone): array
    {
        $address = [];

        foreach (self::FIELDS as $field) {
            $getter = 'get_' . $type . '_' . $field;
            $address[$field] = (string) $customer->$getter();
        }

        if ($withPhone) {
            $address['phone'] = (string) $customer->get_billing_phone();
        }

        return $address;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function setAddress(WC_Customer $customer, string $type, array $values, bool $withPhone): void
    {
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $setter = 'set_' . $type . '_' . $field;
            $raw = sanitize_text_field((string) $values[$field]);
            $customer->$setter($field === 'country' ? strtoupper($raw) : $raw);
        }

        if ($withPhone && array_key_exists('phone', $values)) {
            $customer->set_billing_phone(sanitize_text_field((string) $values['phone']));
        }
    }
}
