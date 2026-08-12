<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\SubeSiparis\SubeSiparisModule::boot()}. Same two-layer
 * platform-wide vs. own-branch pattern as every other branch-scoped module
 * (Depo'nun WarehouseCapability'si, Finance'ın hakediş capability'leri).
 */
enum BranchOrderCapability: string
{
    /**
     * Genel Merkez/Bölge Müdürü: her şubenin ücretsiz kota tanımlarını
     * (branch × product) yönetir, her şubenin siparişini görür/onaylar/
     * reddeder.
     */
    case MANAGE_BRANCH_ORDERS = 'scp_manage_branch_orders';

    /**
     * Şube Müdürü: yalnızca KENDİ şubesi için sipariş taslağı oluşturur/
     * düzenler/gönderir/vazgeçer, kendi şubesinin kota durumunu ve sipariş
     * geçmişini görür - başka bir şubenin kotasını/siparişini asla göremez,
     * kendi başına onay veremez (onay yalnızca MANAGE_BRANCH_ORDERS'ta).
     */
    case MANAGE_OWN_BRANCH_ORDERS = 'scp_manage_own_branch_orders';
}
