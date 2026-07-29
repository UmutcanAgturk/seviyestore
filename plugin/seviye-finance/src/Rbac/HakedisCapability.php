<?php

declare(strict_types=1);

namespace Seviye\Finance\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Finance\FinanceModule::boot()} - Finance never touches
 * WordPress' role API directly.
 *
 * Named VIEW rather than MANAGE (the convention every other module's HQ
 * capability uses): this module's REST surface is read-only in this
 * milestone - ledger entries are only ever created by
 * {@see \Seviye\Finance\Support\HakedisEventListener} reacting to
 * Commerce's events, never by a REST write. VIEW_OWN_HAKEDIS is
 * deliberately granted more narrowly than Branches' VIEW_OWN_BRANCH
 * (Şube Müdürü + Muhasebe only, not every branch-scoped role) - a
 * branch's financial balance is more sensitive than its contact details,
 * so the least-privilege set is smaller.
 */
enum HakedisCapability: string
{
    /** Genel Merkez / Bölge Müdürü: view any branch's balance. */
    case VIEW_HAKEDIS = 'scp_view_hakedis';

    /** Şube Müdürü / Muhasebe: view only their own branch's balance. */
    case VIEW_OWN_HAKEDIS = 'scp_view_own_hakedis';
}
