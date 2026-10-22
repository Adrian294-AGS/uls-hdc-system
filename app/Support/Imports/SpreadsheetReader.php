<?php

namespace App\Support\Imports;

use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Reads an uploaded .xlsx (sheet "Data", or the first sheet) or .csv into rows keyed by the
 * import type's column keys. Headers may be the column keys or their labels, in any order.
 */
final class SpreadsheetReader
{
    /**
     * @return list<array{row: int, values: array<string, string|null>}>
     *
     * @throws RuntimeException with a message that can be shown to the user
     */
    public static function read(string $path, string $extension, ImportType $type): array
    {
        $reader = $extension === 'csv' ? new Csv : new Xlsx;
        if ($reader instanceof Csv) {
            $reader->setInputEncoding(Csv::GUESS_ENCODING);
            $reader->setDelimiter(self::guessDelimiter($path));
        } else {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('Data') ?? $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();

        if ($highestRow - 1 > ImportType::MAX_ROWS + 50) {
            throw new RuntimeException('The file has more than '.ImportType::MAX_ROWS.' rows. Please split it into smaller files.');
        }

        $grid = $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().$highestRow, null, false, false, false);
        $spreadsheet->disconnectWorksheets();

        $columns = $type->columns();
        $lookup = [];
        foreach ($columns as $key => $column) {
            $lookup[self::normalizeHeader($key)] = $key;
            $lookup[self::normalizeHeader($column['label'])] = $key;
        }

        $positions = [];
        foreach ($grid[0] ?? [] as $index => $header) {
            $key = $lookup[self::normalizeHeader((string) $header)] ?? null;
            if ($key !== null && ! isset($positions[$key])) {
                $positions[$key] = $index;
            }
        }

        $missing = array_diff(array_keys(array_filter($columns, fn ($column) => $column['required'])), array_keys($positions));
        if ($missing) {
            $labels = array_map(fn ($key) => $columns[$key]['label'], $missing);
            throw new RuntimeException('Missing column(s): '.implode(', ', $labels).'. Please use the template for "'.$type->label().'".');
        }

        $rows = [];
        foreach (array_slice($grid, 1, null, true) as $index => $cells) {
            $values = [];
            foreach (array_keys($columns) as $key) {
                $values[$key] = isset($positions[$key]) ? self::cell($cells[$positions[$key]] ?? null, $key) : null;
            }

            if (collect($values)->filter(fn ($value) => $value !== null && trim($value) !== '')->isEmpty()) {
                continue;
            }

            $rows[] = ['row' => $index + 1, 'values' => $values];
        }

        if ($rows === []) {
            throw new RuntimeException('The file has no data rows under the header row.');
        }

        if (count($rows) > ImportType::MAX_ROWS) {
            throw new RuntimeException('The file has '.count($rows).' rows; the limit is '.ImportType::MAX_ROWS.'. Please split it into smaller files.');
        }

        return $rows;
    }

    private static function cell(mixed $value, string $key): ?string
    {
        if ($value === null) {
            return null;
        }

        // Excel stores dates as serial numbers.
        if (str_ends_with($key, '_date') && is_numeric($value) && (float) $value > 20000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    private static function normalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($header, " \t\n\r\0\x0B\u{FEFF}")));
    }

    private static function guessDelimiter(string $path): string
    {
        $firstLine = (string) fgets(fopen($path, 'r'));

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }
}
