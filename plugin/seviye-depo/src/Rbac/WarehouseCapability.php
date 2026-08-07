<?php

declare(strict_types=1);

namespace Seviye\Depo\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Depo\DepoModule::boot()}.
 *
 * Faz 4 ("bir tane genel merkezin deposu, şube ürün eklemişse kendi
 * deposu") reversed the original "Tek bir depo vardır" design: now
 * Products/Pricing/Orders'ın zaten kullandığı platform-wide vs.
 * own-branch iki katmanlı desenin AYNISI burada da var. Platform-wide
 * capability'ler (üstteki dört + iki Faz 2 case'i) Genel Merkez/Bölge
 * Müdürü/Depo rolüne HER depoyu (Genel Merkez'in kendi deposu + her
 * şubenin kendi deposu) görme/yönetme yetkisi verir - onlar için "tek
 * depo" hâlâ doğru bir zihinsel model, sadece artık birden fazla depo
 * VAR ve hepsini görebiliyorlar. Own-branch case'ler ise yalnızca Şube
 * Müdürü'ne, YALNIZCA KENDİ şubesinin deposunu (yalnızca o şubenin
 * eklediği ürünleri) yönetme yetkisi verir - bkz. her REST controller'ın
 * kendi resolveWarehouseBranchScope() benzeri metodunun docblock'u.
 * Tedarikçi listesi kasıtlı olarak İKİ KATMANLI DEĞİL - platform genelinde
 * TEK bir tedarikçi dizini var, bir şube kendi tedarikçisini eklemiyor,
 * yalnızca var olan tedarikçilere karşı kendi siparişini açıyor (bkz.
 * SuppliersRestController'ın kendi docblock'u).
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

    /**
     * Faz 4: Şube Müdürü - yalnızca kendi şubesinin eklediği ürünlere ait
     * satın alma siparişlerini açma/gönderme/iptal etme/mal kabul.
     */
    case MANAGE_OWN_BRANCH_PURCHASE_ORDERS = 'scp_manage_own_branch_purchase_orders';

    /** Faz 4: Şube Müdürü - yalnızca kendi şubesinin stok hareketi defterini görme. */
    case VIEW_OWN_BRANCH_STOCK_MOVEMENTS = 'scp_view_own_branch_stock_movements';

    /** Faz 4: Şube Müdürü - yalnızca kendi şubesinin deposunda stok sayımı. */
    case MANAGE_OWN_BRANCH_STOCK_COUNTS = 'scp_manage_own_branch_stock_counts';

    /** Faz 4: Şube Müdürü - yalnızca kendi şubesinin düşük stok önerileri. */
    case MANAGE_OWN_BRANCH_PURCHASE_SUGGESTIONS = 'scp_manage_own_branch_purchase_suggestions';
}
