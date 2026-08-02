<?php

declare(strict_types=1);

namespace Seviye\Security\Privacy;

/**
 * Pure assembly of the KVKK veri ihracı JSON structure - the actual
 * gathering (WP user row, scp_user_identities, Parents/Students Contracts)
 * happens in Http\PrivacyRequestsRestController, which touches the
 * platform; this class only shapes already-fetched plain arrays into the
 * final export document, the same "Support classes stay pure, Http classes
 * touch the platform" split every other module in this codebase follows
 * (see Seviye\Reports\Support\SalesReportBuilder's docblock).
 *
 * Deliberately excludes order/hakediş history - that data is Commerce's,
 * and this platform must retain it regardless of a privacy request (TR
 * Vergi Usul Kanunu financial-record retention); pulling it in here would
 * also require Security to newly depend on Commerce/WooCommerce, a much
 * larger change than this feature's scope justifies. Documented here
 * rather than silently omitted.
 */
final class PrivacyExportBuilder
{
    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed>|null $identity null if this user has no
     *     scp_user_identities row (e.g. never completed first-setup)
     * @param array<string, mixed>|null $parentProfile null if this user has
     *     no scp_parent_profiles row (not a veli, or one with nothing saved)
     * @param list<array<string, mixed>> $children empty if this user has no
     *     linked öğrenci
     * @return array<string, mixed>
     */
    public function build(
        string $generatedAt,
        array $account,
        ?array $identity,
        ?array $parentProfile,
        array $children
    ): array {
        return [
            'generated_at' => $generatedAt,
            'account' => $account,
            'identity' => $identity,
            'parent_profile' => $parentProfile,
            'children' => $children,
        ];
    }
}
