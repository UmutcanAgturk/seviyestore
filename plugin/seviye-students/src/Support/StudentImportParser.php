<?php

declare(strict_types=1);

namespace Seviye\Students\Support;

/**
 * Parses a "toplu öğrenci kaydı" (bulk student registration) CSV into
 * {@see StudentImportRow} rows - kept free of any WordPress/WooCommerce
 * call (only structural parsing: header mapping, required-field presence)
 * so it is fully unit-testable, the same "Support classes stay pure, Http
 * classes touch the platform" split every other module in this codebase
 * follows (see Seviye\Reports\Support\SalesReportBuilder's docblock).
 * Business-rule validation (a malformed education year, TC No) happens
 * later in StudentsRestController::import(), against the actual value
 * objects - this parser only rejects rows that are structurally unusable.
 *
 * Expects a header row naming columns (case-insensitive, order-independent):
 * first_name, last_name, education_year, class_name, and an optional tc_no.
 * Extra/unknown columns are silently ignored, not rejected.
 */
final class StudentImportParser
{
    private const REQUIRED_COLUMNS = ['first_name', 'last_name', 'education_year', 'class_name'];

    /**
     * @return list<StudentImportRow>
     */
    public function parse(string $csv): array
    {
        // Strip a UTF-8 BOM if present - Excel-exported CSVs on Windows
        // carry one (the same reason CsvExporter::export() writes one).
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
    private function parseRow(int $lineNumber, string $line, array $header): StudentImportRow
    {
        $columns = str_getcsv($line, ',', '"', '\\');

        if (count($columns) !== count($header)) {
            return new StudentImportRow($lineNumber, '', '', '', '', null, $this->columnCountError());
        }

        $values = array_combine($header, array_map('trim', $columns));

        $missing = array_values(array_filter(
            self::REQUIRED_COLUMNS,
            static fn (string $column): bool => ($values[$column] ?? '') === ''
        ));

        if ($missing !== []) {
            return new StudentImportRow($lineNumber, '', '', '', '', null, $this->missingFieldsError($missing));
        }

        $tcNo = trim((string) ($values['tc_no'] ?? ''));

        return new StudentImportRow(
            $lineNumber,
            $values['first_name'],
            $values['last_name'],
            $values['education_year'],
            $values['class_name'],
            $tcNo !== '' ? $tcNo : null,
            null
        );
    }

    private function columnCountError(): string
    {
        return function_exists('__')
            ? __('Sütun sayısı başlık satırıyla uyuşmuyor.', 'seviye-students')
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
            ? sprintf(__('Zorunlu alan(lar) boş: %s', 'seviye-students'), $fields)
            : sprintf('Zorunlu alan(lar) boş: %s', $fields);
    }
}
