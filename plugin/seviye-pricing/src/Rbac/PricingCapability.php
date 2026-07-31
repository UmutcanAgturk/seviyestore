<?php

declare(strict_types=1);

namespace Seviye\Pricing\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Pricing\PricingModule::boot()} - Pricing never touches
 * WordPress' role API directly. MANAGE_PRICING is the general "can touch
 * this panel at all" capability, exactly like Students' scp_manage_students:
 * the "which scopes can this user touch" question is mostly answered at
 * request time by {@see \Seviye\Pricing\Http\PricingRestController}, not by
 * a second capability per scope.
 *
 * MANAGE_BASE_PRICING is the one exception: a GENERAL-scope rule is the
 * platform-wide floor every BRANCH/STUDENT rule for that product must not
 * undercut, so touching it is deliberately narrower than MANAGE_PRICING -
 * Genel Merkez and Sistem only, not Bölge Müdürü/Şube Müdürü even though
 * they hold MANAGE_PRICING.
 */
enum PricingCapability: string
{
    case MANAGE_PRICING = 'scp_manage_pricing';
    case MANAGE_BASE_PRICING = 'scp_manage_base_pricing';
}
