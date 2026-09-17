<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report;

use RuntimeException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CsvToXlsxConverter
{
    public function convert(string $csv): string
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'report_csv_');
        $xlsxPath = tempnam(sys_get_temp_dir(), 'report_xlsx_');
        $spreadsheet = null;

        if ($csvPath === false || $xlsxPath === false) {
            if (is_string($csvPath)) {
                @unlink($csvPath);
            }

            if (is_string($xlsxPath)) {
                @unlink($xlsxPath);
            }

            throw new RuntimeException('Unable to create temporary report files.');
        }

        try {
            if (file_put_contents($csvPath, $csv) === false) {
                throw new RuntimeException('Unable to write temporary CSV report.');
            }

            $reader = new Csv();
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);

            $spreadsheet = $reader->load($csvPath);

            $writer = new Xlsx($spreadsheet);
            $writer->save($xlsxPath);

            $contents = file_get_contents($xlsxPath);

            if ($contents === false) {
                throw new RuntimeException('Unable to read generated XLSX report.');
            }

            return $contents;
        } finally {
            if ($spreadsheet instanceof Spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }

            @unlink($csvPath);
            @unlink($xlsxPath);
        }
    }
}
