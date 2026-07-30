<?php

declare(strict_types=1);

namespace Seviye\Reports\Rbac;

/**
 * Mirrors Seviye\Finance\Rbac\HakedisCapability's VIEW_HAKEDIS/
 * VIEW_OWN_HAKEDIS split exactly: HQ roles may report on any branch,
 * Şube Müdürü only their own. Named VIEW (not MANAGE) since this module's
 * REST surface is read-only by nature - a report is generated, never
 * edited.
 */
enum ReportCapability: string
{
    /** Genel Merkez / Bölge Müdürü: report on any branch. */
    case VIEW_REPORTS = 'scp_view_reports';

    /** Şube Müdürü: report on their own branch only. */
    case VIEW_OWN_REPORTS = 'scp_view_own_reports';
}
