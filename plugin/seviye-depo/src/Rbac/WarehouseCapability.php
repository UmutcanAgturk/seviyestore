<?php

declare(strict_types=1);

namespace Seviye\Depo\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Depo\DepoModule::boot()}. Unlike Products/Pricing, there is
 * no branch-scoped tier here at all: "Tek bir depo vardır" - the platform
 * operates a single central warehouse, not one per branch, so Depo is a
 * platform-wide role/concern, not something Şube Müdürü ever touches.
 * Genel Merkez + Bölge Müdürü hold every capability for oversight; Depo
 * holds them all because it is literally their job.
 */
enum WarehouseCapability: string
{
    case MANAGE_SUPPLIERS = 'scp_manage_suppliers';
    case MANAGE_PURCHASE_ORDERS = 'scp_manage_purchase_orders';
    case RECEIVE_STOCK = 'scp_receive_stock';
    case VIEW_STOCK_MOVEMENTS = 'scp_view_stock_movements';

    /** Faz 2: stok sayımı (cycle count) açma/sayma/tamamlama. */
    case MANAGE_STOCK_COUNTS = 'scp_manage_stock_counts';

    /** Faz 2: düşük stok satın alma önerilerini görme/reddetme/siparişe çevirme. */
    case MANAGE_PURCHASE_SUGGESTIONS = 'scp_manage_purchase_suggestions';
}
