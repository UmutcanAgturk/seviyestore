<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Seviye\Pricing\Support\XlsxToCsvConverter;
use ZipArchive;

final class XlsxToCsvConverterTest extends TestCase
{
    public function testConvertsInlineStringSheetToCsv(): void
    {
        $xlsx = $this->buildWorkbook(
            "<row r=\"1\">"
            . "<c r=\"A1\" t=\"inlineStr\"><is><t>product_id</t></is></c>"
            . "<c r=\"B1\" t=\"inlineStr\"><is><t>scope</t></is></c>"
            . "<c r=\"C1\" t=\"inlineStr\"><is><t>price</t></is></c>"
            . "</row>"
            . "<row r=\"2\">"
            . "<c r=\"A2\"><v>12</v></c>"
            . "<c r=\"B2\" t=\"inlineStr\"><is><t>general</t></is></c>"
            . "<c r=\"C2\"><v>99.9</v></c>"
            . '</row>',
            null
        );

        $csv = (new XlsxToCsvConverter())->convert($xlsx);

        self::assertSame(
            "product_id,scope,price\n12,general,99.9",
            $csv
        );
    }

    public function testConvertsSharedStringSheetToCsv(): void
    {
        $sharedStrings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2" uniqueCount="2">'
            . '<si><t>product_id</t></si>'
            . '<si><t>branch</t></si>'
            . '</sst>';

        $xlsx = $this->buildWorkbook(
            '<row r="1"><c r="A1" t="s"><v>0</v></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>1</v></c><c r="B2"><v>7</v></c></row>',
            $sharedStrings
        );

        $csv = (new XlsxToCsvConverter())->convert($xlsx);

        self::assertSame("product_id\nbranch,7", $csv);
    }

    public function testQuotesValuesContainingCommas(): void
    {
        $xlsx = $this->buildWorkbook(
            '<row r="1"><c r="A1" t="inlineStr"><is><t>a,b</t></is></c></row>',
            null
        );

        $csv = (new XlsxToCsvConverter())->convert($xlsx);

        self::assertSame('"a,b"', $csv);
    }

    public function testThrowsForANonXlsxFile(): void
    {
        $this->expectException(RuntimeException::class);

        (new XlsxToCsvConverter())->convert('not a zip file at all');
    }

    private function buildWorkbook(string $sheetRowsXml, ?string $sharedStringsXml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'scp_xlsx_test_');
        self::assertNotFalse($path);

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRowsXml . '</sheetData>'
            . '</worksheet>'
        );

        if ($sharedStringsXml !== null) {
            $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        }

        $zip->close();

        $contents = file_get_contents($path);
        self::assertNotFalse($contents);
        unlink($path);

        return $contents;
    }
}
