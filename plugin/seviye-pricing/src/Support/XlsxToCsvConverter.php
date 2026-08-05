<?php

declare(strict_types=1);

namespace Seviye\Pricing\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Converts an uploaded .xlsx workbook's FIRST sheet into a plain CSV
 * string, so PriceRuleImportParser only ever has to understand one format -
 * "Toplu içeri aktarma... sadece csv dosyası değil excel olarak da
 * yüklenebilsin". Reads with PHP's own ZipArchive + SimpleXMLElement (both
 * bundled with PHP, no Composer dependency) rather than a spreadsheet
 * library like PhpSpreadsheet - the same "avoid a heavy dependency for a
 * narrow, well-understood file format" reasoning
 * Seviye\Reports\Support\XlsxExporter documents for the WRITING side (see
 * docs/ARCHITECTURE.md bölüm 16); ZipArchive itself is already an
 * established dependency there, not a new one.
 *
 * Understands the two cell-value shapes any real workbook uses: a shared
 * string reference (`t="s"`, the shape Excel/LibreOffice/Google Sheets
 * write for text) and an inline string (`t="inlineStr"`, the shape
 * XlsxExporter's OWN writer uses) - plus plain numeric cells (no `t`
 * attribute). No styles/formulas/multiple-sheet support - a bulk-import
 * template has none of those.
 */
final class XlsxToCsvConverter
{
    public function convert(string $binaryContent): string
    {
        $path = tempnam(sys_get_temp_dir(), 'scp_xlsx_import_');

        if ($path === false || file_put_contents($path, $binaryContent) === false) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new RuntimeException($this->fileSaveError());
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($path) !== true) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
                throw new RuntimeException($this->invalidZipError());
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($sheetXml === false) {
                $zip->close();

                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
                throw new RuntimeException($this->noSheetError());
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $zip->close();

            return $this->sheetXmlToCsv($sheetXml, $sharedStrings);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $doc = $this->parseXml($xml);
        $strings = [];

        foreach ($doc->si as $entry) {
            $strings[] = $this->siText($entry);
        }

        return $strings;
    }

    /**
     * A shared-string entry is either a plain `<si><t>text</t></si>` or, for
     * rich (multi-run) text, `<si><r><t>...</t></r><r><t>...</t></r></si>` -
     * this concatenates every run so only the plain text survives, no
     * per-run formatting (irrelevant to a bulk-import template anyway).
     */
    private function siText(SimpleXMLElement $entry): string
    {
        if (isset($entry->t)) {
            return (string) $entry->t;
        }

        $text = '';

        foreach ($entry->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function sheetXmlToCsv(string $sheetXml, array $sharedStrings): string
    {
        $doc = $this->parseXml($sheetXml);
        $lines = [];

        foreach ($doc->sheetData->row as $row) {
            $lines[] = $this->rowToCsvLine($row, $sharedStrings);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function rowToCsvLine(SimpleXMLElement $row, array $sharedStrings): string
    {
        $valuesByColumn = [];
        $maxColumnIndex = -1;

        foreach ($row->c as $cell) {
            $columnIndex = $this->columnIndexFromReference((string) $cell['r']);
            $valuesByColumn[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            $maxColumnIndex = max($maxColumnIndex, $columnIndex);
        }

        $line = [];

        for ($column = 0; $column <= $maxColumnIndex; $column++) {
            $line[] = $valuesByColumn[$column] ?? '';
        }

        return $this->csvEscapeLine($line);
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            $index = isset($cell->v) ? (int) $cell->v : -1;

            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            return isset($cell->is->t) ? (string) $cell->is->t : '';
        }

        return isset($cell->v) ? (string) $cell->v : '';
    }

    /**
     * A plain "B4" (column letters + row number) cell reference - only the
     * leading letters (the column) matter here, spreadsheet software always
     * emits the row's own number too but rowToCsvLine() already knows which
     * <row> it's in.
     */
    private function columnIndexFromReference(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * @param list<string> $line
     */
    private function csvEscapeLine(array $line): string
    {
        return implode(',', array_map(static function (string $value): string {
            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                return '"' . str_replace('"', '""', $value) . '"';
            }

            return $value;
        }, $line));
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $doc = simplexml_load_string($xml);

            if ($doc === false) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
                throw new RuntimeException($this->unreadableXmlError());
            }

            return $doc;
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    // WordPress' own __() isn't loaded under plain PHPUnit (no WP
    // bootstrap) - same function_exists('__') guard
    // Seviye\Pricing\Support\PriceRuleImportParser already uses for its own
    // error strings, so this class stays unit-testable without a WP
    // environment. One method per message (rather than a single helper
    // taking a $text parameter) because WordPress.WP.I18n's sniff requires
    // __()'s first argument to be a literal string it can statically see,
    // not a variable.

    private function fileSaveError(): string
    {
        return function_exists('__')
            ? __('Yüklenen dosya geçici olarak kaydedilemedi.', 'seviye-pricing')
            : 'Yüklenen dosya geçici olarak kaydedilemedi.';
    }

    private function invalidZipError(): string
    {
        return function_exists('__')
            ? __('Dosya geçerli bir Excel (.xlsx) dosyası değil.', 'seviye-pricing')
            : 'Dosya geçerli bir Excel (.xlsx) dosyası değil.';
    }

    private function noSheetError(): string
    {
        return function_exists('__')
            ? __('Excel dosyasında okunabilir bir sayfa bulunamadı.', 'seviye-pricing')
            : 'Excel dosyasında okunabilir bir sayfa bulunamadı.';
    }

    private function unreadableXmlError(): string
    {
        return function_exists('__')
            ? __('Excel dosyasının içeriği okunamadı.', 'seviye-pricing')
            : 'Excel dosyasının içeriği okunamadı.';
    }
}
