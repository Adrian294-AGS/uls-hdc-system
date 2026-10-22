<?php

namespace App\Support\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings as WordSettings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Writes exports to temporary files (the controller streams them and deletes them).
 * Every file carries the university header, "CONFIDENTIAL" on every page, and who generated it.
 */
final class DocumentWriter
{
    public const FORMATS = [
        'pdf' => ['label' => 'PDF', 'mime' => 'application/pdf'],
        'xlsx' => ['label' => 'Excel', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'docx' => ['label' => 'Word', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'csv' => ['label' => 'CSV', 'mime' => 'text/csv'],
    ];

    // ---- Single record ---------------------------------------------------------------------

    public static function recordPdf(RecordDocument $document): string
    {
        $pdf = Pdf::loadView('exports.record-pdf', ['document' => $document])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
        $pdf->render();
        self::stampPageNumbers($pdf->getDomPDF());

        return self::save($pdf->output(), 'pdf');
    }

    public static function recordXlsx(RecordDocument $document): string
    {
        $spreadsheet = self::newSpreadsheet($document->title);
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Record');
        foreach (['A' => 34, 'B' => 18, 'C' => 14, 'D' => 18, 'E' => 18, 'F' => 16, 'G' => 22] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $row = self::sheetHeader($sheet, $document->title, $document->generatedLine, 'G');
        foreach ($document->meta as $label => $value) {
            $row = self::labelValueRow($sheet, $row, $label, $value);
        }
        if ($document->photo) {
            self::sheetPhoto($sheet, $document->photo);
        }

        foreach ($document->sections as $section) {
            if ($section['pageBreak']) {
                $sheet->setBreak('A'.($row - 1), Worksheet::BREAK_ROW);
            }
            $row++;
            self::text($sheet, 'A'.$row, mb_strtoupper($section['title']));
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            ]);
            $row++;

            foreach ($section['fields'] as [$label, $value]) {
                $row = self::labelValueRow($sheet, $row, $label, $value);
            }

            foreach ($section['tables'] as $table) {
                if ($table['title'] !== '') {
                    self::text($sheet, 'A'.$row, $table['title']);
                    $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setItalic(true);
                    $row++;
                }
                $row = self::tableRows($sheet, $row, $table['headers'], $table['rows']);
            }
        }

        return self::saveSpreadsheet($spreadsheet);
    }

    public static function recordDocx(RecordDocument $document): string
    {
        $word = self::newWord();
        $section = self::wordSection($word, $document->title, $document->generatedLine);

        // PhpWord reads the image when the document is saved, so the temporary file must outlive saveWord().
        $photoPath = null;
        if ($document->photo) {
            $photoPath = self::save($document->photo['data'], $document->photo['extension']);
            $section->addImage($photoPath, ['width' => 96, 'height' => 96, 'alignment' => Jc::END]);
        }

        $section->addText($document->studentName, ['bold' => true, 'size' => 14]);
        self::wordFieldTable($section, array_map(null, array_keys($document->meta), array_values($document->meta)));

        foreach ($document->sections as $part) {
            if ($part['pageBreak']) {
                $section->addPageBreak();
            }
            $section->addTextBreak();
            $section->addText(mb_strtoupper($part['title']), ['bold' => true, 'size' => 11, 'color' => '1F2937'], ['shading' => ['fill' => 'E5E7EB'], 'spaceAfter' => 80]);
            if ($part['fields']) {
                self::wordFieldTable($section, $part['fields']);
            }
            foreach ($part['tables'] as $table) {
                if ($table['title'] !== '') {
                    $section->addText($table['title'], ['bold' => true, 'italic' => true], ['spaceBefore' => 120]);
                }
                self::wordDataTable($section, $table['headers'], $table['rows']);
            }
        }

        try {
            return self::saveWord($word);
        } finally {
            if ($photoPath) {
                @unlink($photoPath);
            }
        }
    }

    // ---- Lists ------------------------------------------------------------------------------

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $filters
     */
    public static function listFile(string $format, string $title, array $headers, array $rows, array $filters, string $generatedLine): string
    {
        return match ($format) {
            'xlsx' => self::listXlsx($title, $headers, $rows, $filters, $generatedLine),
            'csv' => self::listCsv($title, $headers, $rows, $filters, $generatedLine),
            'pdf' => self::listPdf($title, $headers, $rows, $filters, $generatedLine),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $filters
     */
    private static function listXlsx(string $title, array $headers, array $rows, array $filters, string $generatedLine): string
    {
        $spreadsheet = self::newSpreadsheet($title);
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Students');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $row = self::sheetHeader($sheet, $title, $generatedLine, $lastColumn);
        self::text($sheet, 'A'.$row, 'Filters: '.self::describeFilters($filters));
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $row += 2;

        $headerRow = $row;
        $row = self::tableRows($sheet, $row, $headers, $rows);
        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}".max($headerRow, $row - 1));
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
        }

        return self::saveSpreadsheet($spreadsheet);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $filters
     */
    private static function listCsv(string $title, array $headers, array $rows, array $filters, string $generatedLine): string
    {
        $path = self::temporaryPath('csv');
        $out = fopen($path, 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ñ correctly.
        $write = fn (array $row) => fputcsv($out, array_map(self::csvCell(...), $row), escape: '');

        $write([self::universityLine()]);
        $write([$title.' · '.RecordDocument::CONFIDENTIAL]);
        $write([$generatedLine]);
        $write(['Filters: '.self::describeFilters($filters)]);
        $write([]);
        $write($headers);
        foreach ($rows as $row) {
            $write($row);
        }
        fclose($out);

        return $path;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $filters
     */
    private static function listPdf(string $title, array $headers, array $rows, array $filters, string $generatedLine): string
    {
        $pdf = Pdf::loadView('exports.list-pdf', compact('title', 'headers', 'rows', 'filters', 'generatedLine'))->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true);
        $pdf->render();
        self::stampPageNumbers($pdf->getDomPDF());

        return self::save($pdf->output(), 'pdf');
    }

    // ---- Helpers ----------------------------------------------------------------------------

    public static function universityLine(): string
    {
        return RecordDocument::UNIVERSITY.' · '.RecordDocument::CENTER.' · '.RecordDocument::CITY;
    }

    /**
     * @param  array<string, string>  $filters
     */
    public static function describeFilters(array $filters): string
    {
        return collect($filters)->map(fn ($value, $label) => "{$label}: {$value}")->implode('; ') ?: 'None (all students you may access)';
    }

    private static function stampPageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 30, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.3, 0.3, 0.3]);
    }

    private static function newSpreadsheet(string $title): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setCreator('ULS HDC')->setTitle($title)->setSubject(RecordDocument::CONFIDENTIAL);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        return $spreadsheet;
    }

    private static function sheetHeader(Worksheet $sheet, string $title, string $generatedLine, string $lastColumn): int
    {
        $lines = [RecordDocument::UNIVERSITY, RecordDocument::CENTER, RecordDocument::CITY, $title, RecordDocument::CONFIDENTIAL, $generatedLine];
        foreach ($lines as $index => $line) {
            $row = $index + 1;
            self::text($sheet, 'A'.$row, $line);
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A5')->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
        $sheet->getStyle('A6')->getFont()->setItalic(true)->setSize(9);

        $sheet->getHeaderFooter()
            ->setOddHeader('&C&B'.RecordDocument::CONFIDENTIAL.'&B'."\n".'&8'.RecordDocument::UNIVERSITY.' · '.RecordDocument::CENTER)
            ->setOddFooter('&L&8'.str_replace('&', '&&', $generatedLine).'&R&8Page &P of &N');
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);

        return count($lines) + 2;
    }

    private static function labelValueRow(Worksheet $sheet, int $row, string $label, string $value): int
    {
        self::text($sheet, 'A'.$row, $label);
        self::text($sheet, 'B'.$row, $value === '' ? '—' : $value);
        $sheet->mergeCells("B{$row}:G{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $lines = max(1, (int) ceil(mb_strlen($value) / 95), substr_count($value, "\n") + 1, (int) ceil(mb_strlen($label) / 32));
        $sheet->getRowDimension($row)->setRowHeight(14 * $lines + 2);

        return $row + 1;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private static function tableRows(Worksheet $sheet, int $row, array $headers, array $rows): int
    {
        $last = Coordinate::stringFromColumnIndex(max(1, count($headers)));
        foreach ($headers as $index => $header) {
            self::text($sheet, [$index + 1, $row], $header);
        }
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ]);
        $start = $row;
        $row++;

        foreach ($rows ?: [array_fill(0, count($headers), '')] as $cells) {
            foreach ($cells as $index => $cell) {
                self::text($sheet, [$index + 1, $row], $cell === '' && $index === 0 && ! $rows ? 'None' : $cell);
            }
            $row++;
        }

        $sheet->getStyle("A{$start}:{$last}".($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
        ]);

        return $row + 1;
    }

    /**
     * Always a plain text cell, so nothing a student typed can run as a formula.
     *
     * @param  string|array{0: int, 1: int}  $cell
     */
    private static function text(Worksheet $sheet, string|array $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    /**
     * @param  array{data: string, mime: string, extension: string}  $photo
     */
    private static function sheetPhoto(Worksheet $sheet, array $photo): void
    {
        $image = @imagecreatefromstring($photo['data']);
        if (! $image) {
            return;
        }

        $drawing = new MemoryDrawing;
        $drawing->setName('2x2 photo')
            ->setImageResource($image)
            ->setRenderingFunction(MemoryDrawing::RENDERING_PNG)
            ->setMimeType(MemoryDrawing::MIMETYPE_PNG)
            ->setHeight(96)
            ->setCoordinates('G7')
            ->setWorksheet($sheet);
    }

    private static function newWord(): PhpWord
    {
        // Escape user text (names can contain "&" or "<").
        WordSettings::setOutputEscapingEnabled(true);

        $word = new PhpWord;
        $word->getDocInfo()->setCreator('ULS HDC')->setSubject(RecordDocument::CONFIDENTIAL);
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);

        return $word;
    }

    private static function wordSection(PhpWord $word, string $title, string $generatedLine): Section
    {
        $section = $word->addSection(['marginTop' => 1700, 'marginBottom' => 1100, 'marginLeft' => 1000, 'marginRight' => 1000]);

        $header = $section->addHeader();
        $center = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];
        $header->addText(RecordDocument::UNIVERSITY, ['bold' => true, 'size' => 12], $center);
        $header->addText(RecordDocument::CENTER, ['bold' => true, 'size' => 11], $center);
        $header->addText(RecordDocument::CITY, ['size' => 10], $center);
        $header->addText(RecordDocument::CONFIDENTIAL, ['bold' => true, 'color' => 'B91C1C', 'size' => 10], $center);

        $footer = $section->addFooter();
        $footer->addPreserveText($generatedLine.'    ·    Page {PAGE} of {NUMPAGES}', ['size' => 8, 'color' => '555555'], ['alignment' => Jc::CENTER]);

        $section->addText($title, ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);

        return $section;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $fields
     */
    private static function wordFieldTable(Section $section, array $fields): void
    {
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'D1D5DB', 'cellMargin' => 60, 'width' => 100 * 50, 'unit' => 'pct']);
        foreach ($fields as [$label, $value]) {
            $table->addRow();
            $table->addCell(3200)->addText($label, ['bold' => true, 'size' => 9]);
            self::wordMultiline($table->addCell(6800), $value === '' ? '—' : $value);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private static function wordDataTable(Section $section, array $headers, array $rows): void
    {
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'D1D5DB', 'cellMargin' => 50, 'width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($headers as $header) {
            $table->addCell(null, ['bgColor' => 'E5E7EB'])->addText($header, ['bold' => true, 'size' => 9]);
        }
        foreach ($rows ?: [['None']] as $cells) {
            $table->addRow();
            foreach (array_pad($cells, count($headers), '') as $cell) {
                self::wordMultiline($table->addCell(), $cell);
            }
        }
    }

    /**
     * One paragraph per line (a bare text break would be written outside a run, which Word rejects).
     */
    private static function wordMultiline(Cell $cell, string $text): void
    {
        foreach (preg_split('/\R/', $text) as $line) {
            $cell->addText($line, ['size' => 9], ['spaceAfter' => 0]);
        }
    }

    private static function saveSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $path = self::temporaryPath('xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private static function saveWord(PhpWord $word): string
    {
        $path = self::temporaryPath('docx');
        WordIOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    private static function save(string $contents, string $extension): string
    {
        $path = self::temporaryPath($extension);
        file_put_contents($path, $contents);

        return $path;
    }

    private static function temporaryPath(string $extension): string
    {
        $directory = storage_path('app/tmp-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        return $directory.'/'.bin2hex(random_bytes(16)).'.'.$extension;
    }

    /**
     * Stop spreadsheet apps from treating a CSV cell as a formula.
     */
    private static function csvCell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
