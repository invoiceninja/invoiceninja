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
use App\Export\CSV\BaseExport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RawRowsXlsxWriter
{
    public function convert(BaseExport $export): ?string
    {
        $rows = $export->rawRows();

        if ($rows === []) {
            return null;
        }

        $headers = $export->spreadsheetHeaders();

        if ($headers === []) {
            $headers = array_keys($rows[0]);
        }

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $xlsxPath = tempnam(sys_get_temp_dir(), 'report_raw_xlsx_');

        if ($xlsxPath === false) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('Unable to create temporary XLSX report file.');
        }

        try {
            $this->writeHeader($worksheet, $headers);
            $this->writeRows($worksheet, $rows, $export);

            $writer = new Xlsx($spreadsheet);
            $writer->save($xlsxPath);

            $contents = file_get_contents($xlsxPath);

            if ($contents === false) {
                throw new RuntimeException('Unable to read generated XLSX report.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($xlsxPath);
        }
    }

    /**
     * @param array<int, string> $headers
     */
    private function writeHeader(Worksheet $worksheet, array $headers): void
    {
        foreach (array_values($headers) as $index => $header) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1) . '1';
            $worksheet->setCellValueExplicit($coordinate, (string) $header, DataType::TYPE_STRING);
        }

        $worksheet->freezePane('A2');
    }

    /**
     * @param array<int, array<int|string, mixed>> $rows
     */
    private function writeRows(Worksheet $worksheet, array $rows, BaseExport $export): void
    {
        foreach (array_values($rows) as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 2);
                $this->writeCell($worksheet, $coordinate, $value, $export);
            }
        }
    }

    private function writeCell(Worksheet $worksheet, string $coordinate, mixed $value, BaseExport $export): void
    {
        if ($value === null || $value === '') {
            $worksheet->setCellValueExplicit($coordinate, null, DataType::TYPE_NULL);

            return;
        }

        if (is_bool($value)) {
            $worksheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_BOOL);

            return;
        }

        if (is_int($value) || (is_float($value) && ! is_nan($value) && ! is_infinite($value))) {
            $worksheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
            $worksheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($this->numberFormat($export, $value));

            return;
        }

        $worksheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
    }

    private function numberFormat(BaseExport $export, int|float $value): string
    {
        if (is_int($value)) {
            return '0';
        }

        $currency = $export->company->currency();
        $precision = max(0, (int) ($currency->precision ?? 2));
        $decimal_places = str_repeat('0', $precision);

        return '#,##0' . ($precision > 0 ? '.' . $decimal_places : '');
    }
}