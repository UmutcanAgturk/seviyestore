<?php

declare(strict_types=1);

namespace Seviye\Pricing\Support;

/**
 * One parsed CSV line from a toplu (bulk) fiyat kuralı import - see
 * {@see PriceRuleImportParser}. `error` is set when the row is
 * structurally malformed (wrong column count, a required field left
 * empty, an unrecognized scope); when set, every other field is
 * meaningless and PricingRestController::import() reports the error
 * without attempting to create a rule. Mirrors
 * Seviye\Students\Support\StudentImportRow exactly - business-rule errors
 * (invalid price, scope target the caller isn't allowed to write, a
 * duplicate active rule) are only discovered later, when the row is
 * actually handed to the same rule-creation path store() uses, since that
 * requires the current user's RBAC context this pure parser has no
 * business knowing about.
 */
final class PriceRuleImportRow
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $productId,
        public readonly string $scope,
        public readonly ?string $targetId,
        public readonly string $price,
        public readonly ?string $error
    ) {
    }
}
