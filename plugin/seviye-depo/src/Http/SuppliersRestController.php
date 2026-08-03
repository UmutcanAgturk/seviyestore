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

        [$userId, $error] = $this->resolvePortalUserId($request);

        if ($error !== null) {
            return new WP_REST_Response(['message' => $error], 422);
        }

        $supplier = $this->suppliers->create(
            $name,
            $this->nullableParam($request, 'contact_name'),
            $this->nullableParam($request, 'phone'),
            $this->nullableParam($request, 'email'),
            $this->nullableParam($request, 'tax_number'),
            $this->nullableParam($request, 'address'),
            $userId
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

        [$userId, $error] = $this->resolvePortalUserId($request);

        if ($error !== null) {
            return new WP_REST_Response(['message' => $error], 422);
        }

        $supplier = $this->suppliers->update(
            (int) $request->get_param('id'),
            $name,
            $this->nullableParam($request, 'contact_name'),
            $this->nullableParam($request, 'phone'),
            $this->nullableParam($request, 'email'),
            $this->nullableParam($request, 'tax_number'),
            $this->nullableParam($request, 'address'),
            $status,
            $userId
        );

        return new WP_REST_Response($this->serialize($supplier));
    }

    /**
     * "Tedarikçi portalı" - HQ, tedarikçiyi bir WP hesabına kullanıcı
     * adı/e-postasıyla bağlar (ham bir kullanıcı ID'si girdirmek yerine -
     * daha az hataya açık). Boş bırakılırsa bağlantı kaldırılır
     * (user_id null). Çözülemeyen bir değer sessizce yok sayılmak yerine
     * net bir hata döner - aksi halde HQ'nun yazım hatası fark edilmeden
     * "bağlantısız" bir tedarikçi oluştururdu.
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function resolvePortalUserId(WP_REST_Request $request): array
    {
        $identifier = trim((string) ($request->get_param('user_email') ?? ''));

        if ($identifier === '') {
            return [null, null];
        }

        $user = get_user_by('email', $identifier) ?: get_user_by('login', $identifier);

        if ($user === false) {
            $message = __('Bu kullanıcı adı/e-posta ile eşleşen bir WordPress hesabı bulunamadı.', 'seviye-depo');

            return [null, $message];
        }

        return [(int) $user->ID, null];
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
        $portalUser = $supplier->userId !== null ? get_userdata($supplier->userId) : false;

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_name' => $supplier->contactName,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'tax_number' => $supplier->taxNumber,
            'address' => $supplier->address,
            'status' => $supplier->status->value,
            'portal_user_email' => $portalUser !== false ? $portalUser->user_email : null,
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
            'user_email' => ['required' => false, 'type' => 'string'],
        ];
    }
}
