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
 *
 * MANAGE_TAX_RATES is deliberately SEPARATE from (and narrower than)
 * MANAGE_PRODUCTS: defining/renaming/removing the named tax rate CATALOG
 * (see {@see \Seviye\Commerce\Http\TaxRatesRestController}) is a legal/
 * store-wide setting, HQ-only with no Şube Müdürü tier - but ASSIGNING an
 * already-defined rate to one product stays part of MANAGE_PRODUCTS (a
 * Şube Müdürü may pick from the list HQ already defined, same as they
 * already set that product's price/category, just never add/edit/remove a
 * rate from the list itself).
 */
enum ProductCapability: string
{
    case MANAGE_PRODUCTS = 'scp_manage_products';
    case VIEW_PRODUCTS = 'scp_view_products';
    case MANAGE_TAX_RATES = 'scp_manage_tax_rates';
}
