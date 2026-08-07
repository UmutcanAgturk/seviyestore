<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

use Seviye\Reports\Domain\SalesReportRow;
use Seviye\Reports\Domain\WarehouseReportRow;

/**
 * Plain RFC 4180 CSV, no external dependency - `fputcsv()` against an
 * in-memory stream (`php://temp`) is already exactly correct for this
 * (quoting, escaping), there is nothing a library would add here. A UTF-8
 * BOM is prefixed so Excel on Windows (which otherwise guesses the wrong
 * encoding for a BOM-less UTF-8 CSV) renders Turkish characters correctly.
 */
final class CsvExporter
{
    /**
     * @param list<SalesReportRow> $rows
     */
    public function export(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, [
            'Şube', 'Ürün', 'Sipariş Sayısı', 'Toplam Tutar (TRY)', 'Toplam KDV (TRY)',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, [
                self::neutralizeFormula($row->branchName),
                self::neutralizeFormula($row->productName),
                (string) $row->orderCount,
                number_format($row->totalPrice, 2, '.', ''),
                number_format($row->totalVat, 2, '.', ''),
            ], ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param list<WarehouseReportRow> $rows
     */
    public function exportWarehouse(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, [
            'Tedarikçi', 'Depo', 'Sipariş Sayısı', 'Toplam Tutar (TRY)', 'Tamamlanan Sipariş', 'Zamanında Teslim Oranı (%)',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, [
                self::neutralizeFormula($row->supplierName),
                self::neutralizeFormula($row->branchName),
                (string) $row->orderCount,
                number_format($row->totalCost, 2, '.', ''),
                (string) $row->completedOrderCount,
                $row->onTimeRate === null ? '—' : number_format($row->onTimeRate, 1, '.', ''),
            ], ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /**
     * Branch/product/supplier names come from user-editable catalog data
     * (e.g. a Şube Müdürü-created product name, see ProductsRestController)
     * and flow into a file a higher-trust HQ user opens in Excel/LibreOffice/
     * Sheets. Those applications treat a cell starting with =, +, -, or @ as
     * a formula regardless of correct CSV quoting, so a crafted name could
     * run a formula (e.g. HYPERLINK-based data exfiltration) in the
     * report-opener's spreadsheet app. Prefixing a single quote is the
     * standard OWASP CSV-injection mitigation - it forces the cell to be
     * read as literal text.
     */
    private static function neutralizeFormula(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
