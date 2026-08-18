<?php

namespace App\Support;

/**
 * Minimal, dependency-free .xlsx writer.
 *
 * Builds a single-sheet Excel workbook by hand-assembling the OOXML parts
 * and zipping them with PHP's built-in ZipArchive extension. No Composer
 * package (e.g. maatwebsite/excel or phpoffice/phpspreadsheet) required.
 *
 * Usage:
 *   $rows = [
 *       ['type' => 'text', 'value' => 'School: Nakaseke Progressive SS', 'bold' => true],
 *       ...
 *   ];
 *   XlsxWriter::download('candidate_scores.xlsx', $sheetTitle, $rows);
 */
class XlsxWriter
{
    /**
     * Build the workbook and stream it straight to the browser as a download.
     *
     * @param string $filename    e.g. "chemistry_scores.xlsx"
     * @param string $sheetTitle  Excel sheet/tab name (max 31 chars, no : \ / ? * [ ])
     * @param array  $rows        Array of rows. Each row is an array of cells.
     *                            Each cell is either a raw scalar (string|int|float|null)
     *                            or an assoc array: ['value' => ..., 'bold' => bool, 'align' => 'left'|'center']
     * @param array  $colWidths   Optional column widths (1-indexed => width in chars)
     */
    public static function download(string $filename, string $sheetTitle, array $rows, array $colWidths = []): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = self::build($sheetTitle, $rows, $colWidths);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Build the .xlsx file on disk and return its path.
     */
    public static function build(string $sheetTitle, array $rows, array $colWidths = []): string
    {
        $sheetTitle = self::sanitizeSheetTitle($sheetTitle);
        $tmpDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
        mkdir($tmpDir, 0777, true);
        mkdir($tmpDir . '/_rels', 0777, true);
        mkdir($tmpDir . '/xl', 0777, true);
        mkdir($tmpDir . '/xl/_rels', 0777, true);
        mkdir($tmpDir . '/xl/worksheets', 0777, true);

        file_put_contents($tmpDir . '/[Content_Types].xml', self::contentTypesXml());
        file_put_contents($tmpDir . '/_rels/.rels', self::rootRelsXml());
        file_put_contents($tmpDir . '/xl/workbook.xml', self::workbookXml($sheetTitle));
        file_put_contents($tmpDir . '/xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        file_put_contents($tmpDir . '/xl/styles.xml', self::stylesXml());
        file_put_contents($tmpDir . '/xl/worksheets/sheet1.xml', self::sheetXml($rows, $colWidths));

        $zipPath = sys_get_temp_dir() . '/xlsx_' . uniqid() . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = self::collectFiles($tmpDir);
        foreach ($files as $absolute => $relative) {
            $zip->addFile($absolute, $relative);
        }
        $zip->close();

        self::rrmdir($tmpDir);

        return $zipPath;
    }

    private static function collectFiles(string $dir): array
    {
        $result = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace($dir, '', $absolute), '/\\');
            $relative = str_replace('\\', '/', $relative);
            $result[$absolute] = $relative;
        }
        return $result;
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private static function sanitizeSheetTitle(string $title): string
    {
        $title = preg_replace('/[:\\\\\/\?\*\[\]]/', ' ', $title);
        $title = trim($title);
        if ($title === '') {
            $title = 'Sheet1';
        }
        return mb_substr($title, 0, 31);
    }

    private static function contentTypesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private static function rootRelsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private static function workbookXml(string $sheetTitle): string
    {
        $safeTitle = htmlspecialchars($sheetTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="{$safeTitle}" sheetId="1" r:id="rId1"/>
</sheets>
</workbook>
XML;
    }

    private static function workbookRelsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    /**
     * Two cell formats defined:
     *   s="0" — normal text
     *   s="1" — bold
     *   s="2" — bold + larger (used for the sheet title row)
     */
    private static function stylesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="3">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="13"/><name val="Calibri"/></font>
</fonts>
<fills count="2">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
</fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="3">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
</cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML;
    }

    /**
     * Converts a zero-based column index (0, 1, 2, ...) to an Excel column
     * letter (A, B, ..., Z, AA, AB, ...).
     */
    private static function colLetter(int $indexZeroBased): string
    {
        $n = $indexZeroBased + 1; // work in 1-based for the standard bijective-base-26 algorithm
        $letter = '';
        while ($n > 0) {
            $rem = ($n - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $n = intdiv($n - $rem, 26);
        }
        return $letter;
    }

    private static function sheetXml(array $rows, array $colWidths = []): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if (!empty($colWidths)) {
            $xml .= '<cols>';
            foreach ($colWidths as $colIndex1based => $width) {
                $xml .= '<col min="' . (int) $colIndex1based . '" max="' . (int) $colIndex1based
                    . '" width="' . (float) $width . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        foreach ($rows as $rowIndexZeroBased => $row) {
            $rowNum = $rowIndexZeroBased + 1;
            $xml .= '<row r="' . $rowNum . '">';

            foreach ($row as $colIndexZeroBased => $cell) {
                $colRef = self::colLetter($colIndexZeroBased) . $rowNum;

                // Normalize cell to ['value' => ..., 'bold' => bool, 'style' => int|null]
                if (is_array($cell)) {
                    $value = $cell['value'] ?? null;
                    $styleIdx = $cell['style'] ?? (!empty($cell['bold']) ? 1 : 0);
                } else {
                    $value = $cell;
                    $styleIdx = 0;
                }

                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $colRef . '" s="' . $styleIdx . '"/>';
                    continue;
                }

                if (is_numeric($value) && !is_string($value)) {
                    $xml .= '<c r="' . $colRef . '" s="' . $styleIdx . '"><v>'
                        . htmlspecialchars((string) $value, ENT_XML1) . '</v></c>';
                } elseif (is_numeric($value) && is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value)) {
                    $xml .= '<c r="' . $colRef . '" s="' . $styleIdx . '"><v>'
                        . htmlspecialchars($value, ENT_XML1) . '</v></c>';
                } else {
                    $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xml .= '<c r="' . $colRef . '" t="inlineStr" s="' . $styleIdx . '"><is><t xml:space="preserve">'
                        . $escaped . '</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }
}
