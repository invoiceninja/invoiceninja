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

namespace Tests\Unit\Report;

use App\DataMapper\CompanySettings;
use App\Export\CSV\BaseExport;
use App\Models\Company;
use App\Services\Report\RawRowsXlsxWriter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class RawRowsXlsxWriterTest extends TestCase
{
    public function test_raw_rows_are_captured_while_csv_rows_remain_formatted(): void
    {
        $export = new FakeRawRowsExport();
        $export->buildHeader();
        $export->captureRawRows();

        $csv_row = $export->addCsvRow([
            'name' => 'Acme',
            'amount' => 1234.56,
            'invoice_number' => '00123',
        ]);

        $this->assertSame(1234.56, $export->rawRows()[0]['amount']);
        $this->assertIsString($csv_row['amount']);
        $this->assertNotSame(1234.56, $csv_row['amount']);

        $xlsx = app(RawRowsXlsxWriter::class)->convert($export);
        $this->assertIsString($xlsx);

        $spreadsheet = $this->loadXlsx($xlsx);
        $worksheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Acme', $worksheet->getCell('A2')->getValue());
        $this->assertEquals(1234.56, $worksheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $worksheet->getCell('B2')->getDataType());
        $this->assertSame('00123', $worksheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $worksheet->getCell('C2')->getDataType());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_grouped_exports_replace_raw_rows_with_grouped_summary_rows(): void
    {
        $export = new FakeRawRowsExport(
            [
                ['category' => 'A', 'amount' => 1.25],
                ['category' => 'A', 'amount' => 2.50],
                ['category' => 'B', 'amount' => 5.25],
            ],
            [
                'report_keys' => ['category', 'amount'],
                'group_by' => 'category',
            ]
        );
        $export->captureRawRows();

        $export->groupedRun();

        $raw_rows = $export->rawRows();
        $this->assertCount(2, $raw_rows);
        $this->assertSame('A', $raw_rows[0]['category']);
        $this->assertEquals(3.75, $raw_rows[0]['amount']);
        $this->assertSame(2, $raw_rows[0]['group.count']);
        $this->assertCount(3, $export->spreadsheetHeaders());

        $xlsx = app(RawRowsXlsxWriter::class)->convert($export);
        $this->assertIsString($xlsx);

        $spreadsheet = $this->loadXlsx($xlsx);
        $worksheet = $spreadsheet->getActiveSheet();

        $this->assertSame('A', $worksheet->getCell('A2')->getValue());
        $this->assertEquals(3.75, $worksheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $worksheet->getCell('B2')->getDataType());
        $this->assertSame(2, (int) $worksheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $worksheet->getCell('C2')->getDataType());

        $spreadsheet->disconnectWorksheets();
    }

    private function loadXlsx(string $xlsx): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'raw_rows_xlsx_test_');

        if ($path === false) {
            $this->fail('Unable to create temporary XLSX test file.');
        }

        try {
            file_put_contents($path, $xlsx);

            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }
}

class FakeRawRowsExport extends BaseExport
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $input
     */
    public function __construct(private array $rows = [], array $input = [])
    {
        $settings = CompanySettings::defaults();
        $this->company = new Company();
        $this->company->settings = $settings;
        $this->input = array_merge([
            'report_keys' => ['name', 'amount', 'invoice_number'],
        ], $input);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function addCsvRow(array $row): array
    {
        return (array) $this->convertFloats($row);
    }

    public function run(): string
    {
        $this->buildHeader();

        foreach ($this->rows as $row) {
            $this->convertFloats($row);
        }

        return '';
    }

    public function buildHeader(): array
    {
        $headers = array_map(static function (string $key): string {
            return ucwords(str_replace(['.', '_'], ' ', $key));
        }, $this->input['report_keys']);

        $this->spreadsheet_headers = $headers;

        return $headers;
    }
}