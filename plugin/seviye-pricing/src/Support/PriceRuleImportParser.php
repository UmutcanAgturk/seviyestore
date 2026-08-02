<?php

declare(strict_types=1);

namespace Seviye\Pricing\Support;

use Seviye\Pricing\Domain\PriceScopeType;

/**
 * Parses a "toplu fiyat kuralı içe aktarma" (bulk price rule import) CSV
 * into {@see PriceRuleImportRow} rows - kept free of any WordPress call
 * (only structural parsing: header mapping, required-field presence, scope
 * name validity) so it is fully unit-testable, the same "Support classes
 * stay pure, Http classes touch the platform" split every other module in
 * this codebase follows. Mirrors
 * Seviye\Students\Support\StudentImportParser almost exactly.
 *
 * Expects a header row naming columns (case-insensitive, order-independent):
 * product_id, scope (general|branch|student), price, and an optional
 * target_id (required by PricingRestController::import() only for
 * branch/student scope - not validated here, since a branch-scoped
 * importer's target_id is silently overridden with their own branch
 * regardless of what the CSV says, exactly like store()'s own
 * resolveScopeForWrite()). Extra/unknown columns are silently ignored.
 */
final class PriceRuleImportParser
{
    private const REQUIRED_COLUMNS = ['product_id', 'scope', 'price'];

    /**
     * @return list<PriceRuleImportRow>
     */
    public function parse(string $csv): array
    {
        // Strip a UTF-8 BOM if present - same reason StudentImportParser does.
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return [];
        }

        $header = array_map(
            static fn (string $column): string => strtolower(trim($column)),
            str_getcsv(array_shift($lines), ',', '"', '\\')
        );

        $rows = [];

        foreach ($lines as $index => $line) {
            $rows[] = $this->parseRow($index + 2, $line, $header);
        }

        return $rows;
    }

    /**
     * @param list<string> $header
     */
    private function parseRow(int $lineNumber, string $line, array $header): PriceRuleImportRow
    {
        $columns = str_getcsv($line, ',', '"', '\\');

        if (count($columns) !== count($header)) {
            return new PriceRuleImportRow($lineNumber, '', '', null, '', $this->columnCountError());
        }

        $values = array_combine($header, array_map('trim', $columns));

        $missing = array_values(array_filter(
            self::REQUIRED_COLUMNS,
            static fn (string $column): bool => ($values[$column] ?? '') === ''
        ));

        if ($missing !== []) {
            return new PriceRuleImportRow($lineNumber, '', '', null, '', $this->missingFieldsError($missing));
        }

        $scope = strtolower($values['scope']);

        if (PriceScopeType::tryFrom($scope) === null) {
            return new PriceRuleImportRow($lineNumber, '', '', null, '', $this->invalidScopeError($values['scope']));
        }

        $targetId = trim((string) ($values['target_id'] ?? ''));

        return new PriceRuleImportRow(
            $lineNumber,
            $values['product_id'],
            $scope,
            $targetId !== '' ? $targetId : null,
            $values['price'],
            null
        );
    }

    private function columnCountError(): string
    {
        return function_exists('__')
            ? __('Sütun sayısı başlık satırıyla uyuşmuyor.', 'seviye-pricing')
            : 'Sütun sayısı başlık satırıyla uyuşmuyor.';
    }

    /**
     * @param list<string> $missing
     */
    private function missingFieldsError(array $missing): string
    {
        $fields = implode(', ', $missing);

        return function_exists('__')
            /* translators: %s: comma-separated list of missing required column names */
            ? sprintf(__('Zorunlu alan(lar) boş: %s', 'seviye-pricing'), $fields)
            : sprintf('Zorunlu alan(lar) boş: %s', $fields);
    }

    private function invalidScopeError(string $scope): string
    {
        return function_exists('__')
            /* translators: %s: the invalid scope value found in the CSV */
            ? sprintf(__('Geçersiz kapsam: %s (general, branch veya student olmalı)', 'seviye-pricing'), $scope)
            : sprintf('Geçersiz kapsam: %s (general, branch veya student olmalı)', $scope);
    }
}
