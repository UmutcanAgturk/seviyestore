<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Rbac\ProductCapability;
use Seviye\Commerce\Support\SizeGuide;
use Seviye\Commerce\Support\SizeGuideRow;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Settings\SettingsRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/size-guide - read/write the "Beden Rehberi" (bkz.
 * Support\SizeGuide). Genel Merkez/Bölge Müdürü only
 * (ProductCapability::MANAGE_SIZE_GUIDE) - same shape as
 * SecuritySettingsRestController's IP allowlist endpoint: GET returns the
 * whole list, PUT REPLACES the whole list (rows have no natural stable
 * id/slug to address individually, unlike tax rates' name-keyed catalog).
 */
final class SizeGuideRestController extends AbstractRestController
{
    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/size-guide', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_SIZE_GUIDE->value),
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_SIZE_GUIDE->value),
                'args' => [
                    'rows' => ['required' => true, 'type' => 'array'],
                ],
            ],
        ]);
    }

    public function show(): WP_REST_Response
    {
        return new WP_REST_Response(['rows' => $this->currentRows()]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $raw = $request->get_param('rows');
        $rows = [];

        foreach ((array) $raw as $entry) {
            $row = SizeGuideRow::fromArray(is_array($entry) ? $entry : []);

            if ($row->label === '') {
                continue;
            }

            $rows[] = $row;
        }

        $this->settings->set(SizeGuide::SETTING_KEY, SizeGuide::serialize($rows));

        return new WP_REST_Response(['rows' => array_map(
            static fn (SizeGuideRow $row): array => $row->toArray(),
            $rows
        )]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currentRows(): array
    {
        $rows = SizeGuide::parse($this->settings->get(SizeGuide::SETTING_KEY));

        return array_map(static fn (SizeGuideRow $row): array => $row->toArray(), $rows);
    }
}
