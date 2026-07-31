<?php

declare(strict_types=1);

namespace Seviye\Commerce\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Commerce\CommerceModule::boot()}. A single shared
 * capability gates access to the "Ürünler" panel at all (list/create/toggle
 * own-branch visibility); which specific actions a caller may perform
 * (full edit/delete vs. own-branch visibility toggle only) is answered at
 * request time by {@see \Seviye\Commerce\Http\ProductsRestController} based
 * on branch membership, exactly like Pricing's MANAGE_PRICING.
 */
enum ProductCapability: string
{
    case MANAGE_PRODUCTS = 'scp_manage_products';
}
