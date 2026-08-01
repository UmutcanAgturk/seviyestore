<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Domain\StockMovement;
use Seviye\Depo\Domain\StockMovementType;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\StockMovementRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/depo/stock-movements - salt okunur defter görüntüleme. Yazma
 * bu uç noktadan asla yapılmaz; her satır PurchaseOrdersRestController::receive()
 * (ya da faz 2'de stok sayımı) tarafından dolaylı olarak üretilir.
 */
final class StockMovementsRestController extends AbstractRestController
{
    public function __construct(private readonly StockMovementRepositoryInterface $movements)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-movements', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => $this->requireCapability(WarehouseCapability::VIEW_STOCK_MOVEMENTS->value),
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $productId = $this->intParam($request, 'product_id');
        $type = StockMovementType::tryFrom((string) ($request->get_param('type') ?? ''));
        $from = $this->stringParam($request, 'from');
        $to = $this->stringParam($request, 'to');

        $movements = array_map($this->serialize(...), $this->movements->list($productId, $type, $from, $to));

        return new WP_REST_Response($movements);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'product_id' => $movement->productId,
            'type' => $movement->type->value,
            'quantity_delta' => $movement->quantityDelta,
            'reference_type' => $movement->referenceType,
            'reference_id' => $movement->referenceId,
            'note' => $movement->note,
            'created_by' => $movement->createdByUserId,
            'created_at' => $movement->createdAt,
        ];
    }

    private function intParam(WP_REST_Request $request, string $name): ?int
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringParam(WP_REST_Request $request, string $name): ?string
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
