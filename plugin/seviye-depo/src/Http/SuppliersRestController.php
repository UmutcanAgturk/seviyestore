<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Domain\Supplier;
use Seviye\Depo\Domain\SupplierStatus;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\SupplierRepositoryInterface;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/depo/suppliers/* - platform-wide, tek bir tedarikçi listesi
 * (bkz. WarehouseCapability - şube bazlı bir kapsam yok).
 */
final class SuppliersRestController extends AbstractRestController
{
    public function __construct(private readonly SupplierRepositoryInterface $suppliers)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/suppliers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(WarehouseCapability::MANAGE_SUPPLIERS->value),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(WarehouseCapability::MANAGE_SUPPLIERS->value),
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/suppliers/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(WarehouseCapability::MANAGE_SUPPLIERS->value),
                'args' => $this->writableArgs(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'destroy'],
                'permission_callback' => $this->requireCapability(WarehouseCapability::MANAGE_SUPPLIERS->value),
            ],
        ]);
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response(array_map($this->serialize(...), $this->suppliers->all()));
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $name = trim((string) $request->get_param('name'));

        if ($name === '') {
            return new WP_REST_Response(['message' => __('Tedarikçi adı gerekli.', 'seviye-depo')], 422);
        }

        $supplier = $this->suppliers->create(
            $name,
            $this->nullableParam($request, 'contact_name'),
            $this->nullableParam($request, 'phone'),
            $this->nullableParam($request, 'email'),
            $this->nullableParam($request, 'tax_number'),
            $this->nullableParam($request, 'address')
        );

        return new WP_REST_Response($this->serialize($supplier), 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $name = trim((string) $request->get_param('name'));

        if ($name === '') {
            return new WP_REST_Response(['message' => __('Tedarikçi adı gerekli.', 'seviye-depo')], 422);
        }

        $status = SupplierStatus::tryFrom((string) ($request->get_param('status') ?? SupplierStatus::ACTIVE->value))
            ?? SupplierStatus::ACTIVE;

        $supplier = $this->suppliers->update(
            (int) $request->get_param('id'),
            $name,
            $this->nullableParam($request, 'contact_name'),
            $this->nullableParam($request, 'phone'),
            $this->nullableParam($request, 'email'),
            $this->nullableParam($request, 'tax_number'),
            $this->nullableParam($request, 'address'),
            $status
        );

        return new WP_REST_Response($this->serialize($supplier));
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $this->suppliers->delete((int) $request->get_param('id'));
        } catch (RuntimeException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 409);
        }

        return new WP_REST_Response(['success' => true]);
    }

    private function nullableParam(WP_REST_Request $request, string $name): ?string
    {
        $value = trim((string) ($request->get_param($name) ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_name' => $supplier->contactName,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'tax_number' => $supplier->taxNumber,
            'address' => $supplier->address,
            'status' => $supplier->status->value,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function writableArgs(): array
    {
        return [
            'name' => ['required' => true, 'type' => 'string'],
            'contact_name' => ['required' => false, 'type' => 'string'],
            'phone' => ['required' => false, 'type' => 'string'],
            'email' => ['required' => false, 'type' => 'string'],
            'tax_number' => ['required' => false, 'type' => 'string'],
            'address' => ['required' => false, 'type' => 'string'],
            'status' => ['required' => false, 'type' => 'string'],
        ];
    }
}
