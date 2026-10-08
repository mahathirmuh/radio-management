<?php

namespace Tests\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Makes the bytes of a small Excel workbook, for the tests of pages that read one.
 */
trait MakesWorkbooks
{
    /**
     * @param array<string, array<int, array<int, mixed>>> $sheets sheet name => rows => cells. A name that starts
     *     with "~" makes a sheet that is hidden in Excel. A cell is a value (a string is written as text, a number
     *     as a number), null for an empty cell, or an array: ['value' => 45000, 'format' => 'd-mmm-yy'] for a
     *     number with a number format, ['formula' => '=A1*2'] for a formula (Excel's result is stored with it).
     */
    protected static function xlsx(array $sheets): string
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        foreach ($sheets as $name => $rows) {
            $sheet = $book->createSheet();
            $sheet->setTitle(ltrim((string) $name, '~'));
            if (str_starts_with((string) $name, '~')) {
                $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
            }

            foreach ($rows as $row => $cells) {
                foreach ($cells as $column => $cell) {
                    $at = [$column + 1, $row + 1];
                    match (true) {
                        $cell === null => null,
                        is_array($cell) && isset($cell['formula']) => $sheet->setCellValueExplicit($at, $cell['formula'], DataType::TYPE_FORMULA),
                        is_array($cell) => $sheet->setCellValueExplicit($at, $cell['value'], DataType::TYPE_NUMERIC),
                        is_string($cell) => $sheet->setCellValueExplicit($at, $cell, DataType::TYPE_STRING),
                        is_bool($cell) => $sheet->setCellValueExplicit($at, $cell, DataType::TYPE_BOOL),
                        default => $sheet->setCellValueExplicit($at, $cell, DataType::TYPE_NUMERIC),
                    };
                    if (is_array($cell) && isset($cell['format'])) {
                        $sheet->getStyle($at)->getNumberFormat()->setFormatCode($cell['format']);
                    }
                }
            }
        }

        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        try {
            (new Xlsx($book))->save($file);

            return (string) file_get_contents($file);
        } finally {
            $book->disconnectWorksheets();
            @unlink($file);
        }
    }
}
