<?php

declare(strict_types=1);

namespace Seviye\Reports\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Reports\Domain\SalesReportRow;
use Seviye\Reports\Support\CsvExporter;

final class CsvExporterTest extends TestCase
{
    public function testExportStartsWithAUtf8Bom(): void
    {
        $csv = (new CsvExporter())->export([]);

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function testExportIncludesTheHeaderRow(): void
    {
        $rows = $this->parseCsv((new CsvExporter())->export([]));

        self::assertSame(
            ['Şube', 'Ürün', 'Sipariş Sayısı', 'Toplam Tutar (TRY)', 'Toplam KDV (TRY)'],
            $rows[0]
        );
    }

    public function testExportIncludesOneLinePerRow(): void
    {
        $rows = [
            new SalesReportRow(7, 'Kadıköy', 55, 'Matematik Kitabı', 3, 269.70, 48.55),
        ];

        $lines = $this->parseCsv((new CsvExporter())->export($rows));

        self::assertSame(['Kadıköy', 'Matematik Kitabı', '3', '269.70', '48.55'], $lines[1]);
    }

    /**
     * @return list<list<string>>
     */
    private function parseCsv(string $csv): array
    {
        // The exporter's UTF-8 BOM is only meaningful to a spreadsheet
        // application, not to str_getcsv() - strip it before parsing.
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($csv)) as $line) {
            $lines[] = str_getcsv($line, ',', '"', '');
        }

        return $lines;
    }

    public function testValuesContainingCommasAreProperlyQuoted(): void
    {
        $rows = [
            new SalesReportRow(7, 'Kadıköy, Merkez', 55, 'Kitap', 1, 10.0, 1.0),
        ];

        $csv = (new CsvExporter())->export($rows);

        self::assertStringContainsString('"Kadıköy, Merkez"', $csv);
    }

    /**
     * A product/branch name is user-editable catalog data (e.g. set by a
     * Şube Müdürü creating a product) that a higher-trust HQ user later
     * opens in Excel/LibreOffice/Sheets - those apps treat a leading
     * =/+/-/@ as a formula regardless of correct CSV quoting, so the
     * exporter must neutralize it (CWE-1236, standard OWASP mitigation).
     */
    public function testProductNamesStartingWithAFormulaCharacterAreNeutralized(): void
    {
        $rows = [
            new SalesReportRow(7, 'Kadıköy', 55, '=HYPERLINK("http://evil.example")', 1, 10.0, 1.0),
        ];

        $lines = $this->parseCsv((new CsvExporter())->export($rows));

        self::assertSame("'=HYPERLINK(\"http://evil.example\")", $lines[1][1]);
    }
}
