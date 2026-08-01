<?php

declare(strict_types=1);

namespace Seviye\Commerce\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Commerce\CommerceModule::boot()}. Unlike ProductCapability,
 * there is only one tier and Şube Müdürü does not hold it: "OKUL2026"-style
 * promo codes are a platform/campaign-level concept (WooCommerce's own
 * shop_coupon post type, shared across the whole catalog), not scoped to a
 * single branch the way products/students are - see
 * {@see \Seviye\Commerce\Http\CouponsRestController}.
 */
enum CouponCapability: string
{
    case MANAGE_COUPONS = 'scp_manage_coupons';
}
