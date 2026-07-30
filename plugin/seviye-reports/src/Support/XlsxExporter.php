<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

use RuntimeException;
use Seviye\Reports\Domain\SalesReportRow;
use ZipArchive;

/**
 * A minimal, hand-written OOXML (.xlsx) writer - deliberately not a
 * Composer dependency like PhpSpreadsheet. That library is large (many
 * transitive dependencies, GD/zip requirements) for what this platform
 * actually needs: one unstyled sheet of strings and numbers. The same
 * "avoid a heavy dependency for a narrow, well-understood file format"
 * reasoning already applied to 2FA's otpauth URI (no QR image library) and
 * TOTP itself (no OTP library) - see docs/ARCHITECTURE.md bölüm 16. Every
 * XML part here is the documented minimum ECMA-376 requires for a
 * spreadsheet Excel/LibreOffice/Google Sheets will open; there is no
 * styles.xml because this sheet uses no formatting.
 *
 * ZipArchive requires a real filesystem path (no in-memory-only mode in
 * standard PHP builds), so this writes to a temp file and reads it back -
 * the temp file never outlives a single export() call.
 */
final class XlsxExporter
{
    /**
     * @param list<SalesReportRow> $rows
     */
    public function export(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'scp_xlsx_');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for XLSX export.');
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));

        $zip->close();

        $contents = file_get_contents($path);
        unlink($path);

        return $contents === false ? '' : $contents;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
            . ' Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Rapor" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
            . ' Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param list<SalesReportRow> $rows
     */
    private function sheetXml(array $rows): string
    {
        $lines = [$this->row(1, ['Şube', 'Ürün', 'Sipariş Sayısı', 'Toplam Tutar (TRY)', 'Toplam KDV (TRY)'])];

        $rowNumber = 2;

        foreach ($rows as $row) {
            $lines[] = $this->row($rowNumber, [
                $row->branchName,
                $row->productName,
                $row->orderCount,
                $row->totalPrice,
                $row->totalVat,
            ]);
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $lines) . '</sheetData>'
            . '</worksheet>';
    }

    /**
     * @param list<int|float|string> $values
     */
    private function row(int $rowNumber, array $values): string
    {
        $cells = '';

        foreach (array_values($values) as $columnIndex => $value) {
            $reference = $this->columnLetter($columnIndex) . $rowNumber;

            if (is_int($value) || is_float($value)) {
                $cells .= sprintf('<c r="%s"><v>%s</v></c>', $reference, $value);
                continue;
            }

            $cells .= sprintf(
                '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                $reference,
                htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf('<row r="%d">%s</row>', $rowNumber, $cells);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        do {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letter;
    }
}
