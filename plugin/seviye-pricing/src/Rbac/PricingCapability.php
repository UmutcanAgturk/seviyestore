<?php

declare(strict_types=1);

namespace Seviye\Pricing\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Pricing\PricingModule::boot()} - Pricing never touches
 * WordPress' role API directly. A single shared capability, exactly like
 * Students' scp_manage_students: the "which scopes can this user touch"
 * question is answered at request time by
 * {@see \Seviye\Pricing\Http\PricingRestController}, not by a second
 * capability per scope.
 */
enum PricingCapability: string
{
    case MANAGE_PRICING = 'scp_manage_pricing';
}
