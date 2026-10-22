<?php

namespace App\Support\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Download templates: headers + one example row. The .xlsx also has an "Instructions" sheet and
 * dropdowns (data validation) for program codes, year levels and descriptors.
 */
final class TemplateBuilder
{
    public static function csv(ImportType $type): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_keys($type->columns()), escape: '');
        fputcsv($out, array_column($type->columns(), 'example'), escape: '');
        rewind($out);

        return (string) stream_get_contents($out);
    }

    public static function xlsx(ImportType $type): string
    {
        $columns = $type->columns();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setCreator('ULS HDC')->setTitle('HDC import template: '.$type->label());

        $data = $spreadsheet->getActiveSheet()->setTitle('Data');
        $lists = $spreadsheet->createSheet()->setTitle('Lists');
        $instructions = $spreadsheet->createSheet()->setTitle('Instructions');

        $index = 1;
        $listColumn = 1;
        foreach ($columns as $key => $column) {
            $letter = Coordinate::stringFromColumnIndex($index);
            self::text($data, $letter.'1', $key);
            self::text($data, $letter.'2', $column['example']);
            $data->getColumnDimension($letter)->setWidth(max(14, mb_strlen($column['label']) + 4));
            $data->getComment($letter.'1')->getText()->createText($column['label'].($column['required'] ? ' (required)' : ' (optional)'));

            // Text format so student numbers keep leading zeros and dates stay as typed.
            $data->getStyle($letter.'2:'.$letter.(ImportType::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('@');

            if ($choices = $type->choices()[$key] ?? null) {
                $listLetter = Coordinate::stringFromColumnIndex($listColumn++);
                self::text($lists, $listLetter.'1', $key);
                foreach (array_values($choices) as $row => $choice) {
                    self::text($lists, $listLetter.($row + 2), (string) $choice);
                }
                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_LIST)
                    ->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setAllowBlank(! $column['required'])
                    ->setShowDropDown(true)
                    ->setShowErrorMessage(true)
                    ->setErrorTitle('Not in the list')
                    ->setError('Choose a value from the dropdown.')
                    ->setFormula1('Lists!$'.$listLetter.'$2:$'.$listLetter.'$'.(count($choices) + 1));
                $data->setDataValidation($letter.'2:'.$letter.(ImportType::MAX_ROWS + 1), $validation);
            }

            $index++;
        }

        $lastLetter = Coordinate::stringFromColumnIndex(count($columns));
        $data->getStyle("A1:{$lastLetter}1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ]);
        $data->getStyle("A2:{$lastLetter}2")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
        $data->freezePane('A2');
        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        self::instructions($instructions, $type);
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'tpl');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $contents = (string) file_get_contents($path);
        unlink($path);

        return $contents;
    }

    private static function instructions(Worksheet $sheet, ImportType $type): void
    {
        $lines = [
            ['ULS Human Development Center: import template for '.$type->label()],
            ['1. Fill in the "Data" sheet, one row per '.($type->key() === 'roster' ? 'student' : 'result').'. Keep the header row exactly as it is.'],
            ['2. Row 2 is an example. Its student number starts with "'.ImportType::EXAMPLE_PREFIX.'", so it is never imported; you may delete it.'],
            ['3. At most '.ImportType::MAX_ROWS.' rows and 5 MB per file. Save as .xlsx (or .csv).'],
            ['4. Upload the file on the Import page. You will see a preview with every problem before anything is saved.'],
            $type->key() === 'roster'
                ? ['5. Each new student\'s temporary password is their student number; they must change it on first login.']
                : ['5. Students must already exist in your colleges. A result for the same student and exam date is skipped.'],
            [],
            ['Column', 'Required', 'What to enter', 'Example'],
        ];
        foreach ($type->columns() as $key => $column) {
            $lines[] = [$key, $column['required'] ? 'Yes' : 'No', $column['description'], $column['example']];
        }

        foreach ($lines as $row => $cells) {
            foreach ($cells as $col => $value) {
                self::text($sheet, [$col + 1, $row + 1], (string) $value);
            }
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A8:D8')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(80);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getStyle('C:C')->getAlignment()->setWrapText(true);
    }

    /**
     * @param  string|array{0: int, 1: int}  $cell
     */
    private static function text(Worksheet $sheet, string|array $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }
}
