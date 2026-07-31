<?php

declare(strict_types=1);

namespace Seviye\Commerce\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Commerce\CommerceModule::boot()}. MANAGE_PRODUCTS gates
 * access to the "Ürünler" panel's create/edit/delete/toggle actions; which
 * specific actions a caller may perform (full edit/delete vs. own-branch
 * visibility toggle only) is answered at request time by
 * {@see \Seviye\Commerce\Http\ProductsRestController} based on branch
 * membership, exactly like Pricing's MANAGE_PRICING.
 *
 * VIEW_PRODUCTS is a separate, read-only capability - Muhasebe/Depo/Sistem
 * need to see the catalog's product ids (e.g. to reference them from
 * external ERP/muhasebe tooling) but must not create, edit, delete, or
 * toggle a branch's visibility. Every MANAGE_PRODUCTS holder already sees
 * everything VIEW_PRODUCTS would show, so the two are checked with OR, not
 * layered.
 */
enum ProductCapability: string
{
    case MANAGE_PRODUCTS = 'scp_manage_products';
    case VIEW_PRODUCTS = 'scp_view_products';
}
