<?php

declare(strict_types=1);

namespace Seviye\Reports\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Reports\Domain\SalesReportRow;
use Seviye\Reports\Support\XlsxExporter;
use ZipArchive;

final class XlsxExporterTest extends TestCase
{
    public function testExportProducesAValidZipWithTheRequiredOoxmlParts(): void
    {
        $bytes = (new XlsxExporter())->export([]);

        $path = tempnam(sys_get_temp_dir(), 'scp_xlsx_test_');
        file_put_contents($path, $bytes);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path) === true);

        self::assertNotFalse($zip->locateName('[Content_Types].xml'));
        self::assertNotFalse($zip->locateName('_rels/.rels'));
        self::assertNotFalse($zip->locateName('xl/workbook.xml'));
        self::assertNotFalse($zip->locateName('xl/_rels/workbook.xml.rels'));
        self::assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));

        $zip->close();
        unlink($path);
    }

    public function testSheetXmlContainsTheHeaderRow(): void
    {
        $sheetXml = $this->sheetXmlFor([]);

        self::assertStringContainsString('Sipariş Sayısı', $sheetXml);
        self::assertStringContainsString('r="A1"', $sheetXml);
    }

    public function testSheetXmlContainsRowDataAsSeparateCells(): void
    {
        $rows = [new SalesReportRow(7, 'Kadıköy', 55, 'Matematik Kitabı', 3, 269.70, 48.55)];

        $sheetXml = $this->sheetXmlFor($rows);

        self::assertStringContainsString('r="A2"', $sheetXml);
        self::assertStringContainsString('Kadıköy', $sheetXml);
        self::assertStringContainsString('<v>269.7</v>', $sheetXml);
    }

    public function testStringValuesAreXmlEscaped(): void
    {
        $rows = [new SalesReportRow(7, 'A & B "Şube"', 55, '<Ürün>', 1, 1.0, 0.0)];

        $sheetXml = $this->sheetXmlFor($rows);

        self::assertStringNotContainsString('A & B', $sheetXml);
        self::assertStringContainsString('A &amp; B', $sheetXml);
        self::assertStringContainsString('&lt;Ürün&gt;', $sheetXml);
    }

    /**
     * See CsvExporterTest::testProductNamesStartingWithAFormulaCharacterAreNeutralized -
     * same CWE-1236 concern applies to the XLSX inline strings.
     */
    public function testProductNamesStartingWithAFormulaCharacterAreNeutralized(): void
    {
        $rows = [new SalesReportRow(7, 'Kadıköy', 55, '=HYPERLINK("http://evil.example")', 1, 1.0, 0.0)];

        $sheetXml = $this->sheetXmlFor($rows);

        self::assertStringContainsString('&apos;=HYPERLINK', $sheetXml);
    }

    /**
     * @param list<SalesReportRow> $rows
     */
    private function sheetXmlFor(array $rows): string
    {
        $bytes = (new XlsxExporter())->export($rows);

        $path = tempnam(sys_get_temp_dir(), 'scp_xlsx_test_');
        file_put_contents($path, $bytes);

        $zip = new ZipArchive();
        $zip->open($path);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        self::assertIsString($sheetXml);

        return $sheetXml;
    }
}
