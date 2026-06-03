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
use App\Export\CSV\ClientExport;
use App\Jobs\Report\SendToAdmin;
use App\Models\Company;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionClass;
use Tests\TestCase;

class SendToAdminReportAttachmentsTest extends TestCase
{
    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function test_csv_report_attachments_include_original_csv_and_readable_xlsx_copy(): void
    {
        $csv = "Name,Amount\n\"Acme, Inc\",123.45\nشركة الاختبار,987.65\n";
        $job = new SendToAdmin(new Company(), [], 'FakeReport', 'report.csv');

        $files = $this->buildReportAttachments($job, $csv);

        $this->assertCount(2, $files);

        $csvAttachment = $this->findAttachment($files, 'report.csv');
        $xlsxAttachment = $this->findAttachment($files, 'report.xlsx');

        $this->assertSame('text/csv', $csvAttachment['mime']);
        $this->assertSame($csv, base64_decode($csvAttachment['file']));

        $this->assertSame(self::XLSX_MIME, $xlsxAttachment['mime']);

        $spreadsheet = $this->loadXlsx(base64_decode($xlsxAttachment['file']));
        $worksheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Name', $worksheet->getCell('A1')->getValue());
        $this->assertSame('Acme, Inc', $worksheet->getCell('A2')->getValue());
        $this->assertSame('شركة الاختبار', $worksheet->getCell('A3')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_csv_report_attachments_use_typed_xlsx_when_raw_rows_are_available(): void
    {
        $company = $this->company();
        $export = new ClientExport($company, ['report_keys' => ['client.name', 'client.balance']]);
        $this->setBaseExportProperty($export, 'spreadsheet_headers', ['Client Name', 'Client Balance']);
        $this->setBaseExportProperty($export, 'raw_rows', [
            ['client.name' => 'Acme', 'client.balance' => 1234.56],
        ]);

        $csv = "Client Name,Client Balance\nAcme,\"1,234.56\"\n";
        $job = new SendToAdmin($company, [], ClientExport::class, 'clients.csv');

        $files = $this->buildReportAttachments($job, $csv, $export);

        $xlsxAttachment = $this->findAttachment($files, 'clients.xlsx');
        $spreadsheet = $this->loadXlsx(base64_decode($xlsxAttachment['file']));
        $worksheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Acme', $worksheet->getCell('A2')->getValue());
        $this->assertEquals(1234.56, $worksheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $worksheet->getCell('B2')->getDataType());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_xlsx_report_attachments_are_not_converted_again(): void
    {
        $job = new SendToAdmin(new Company(), [], 'FakeReport', 'tax_period.xlsx');
        $xlsx = "PK\x03\x04already-xlsx";

        $files = $this->buildReportAttachments($job, $xlsx);

        $this->assertCount(1, $files);
        $this->assertSame('tax_period.xlsx', $files[0]['file_name']);
        $this->assertSame(self::XLSX_MIME, $files[0]['mime']);
        $this->assertSame($xlsx, base64_decode($files[0]['file']));
    }

    /**
     * @return array<int, array{file: string, file_name: string, mime: string}>
     */
    private function buildReportAttachments(SendToAdmin $job, string $contents, ?BaseExport $export = null): array
    {
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('buildReportAttachments');
        $method->setAccessible(true);

        return $method->invoke($job, $contents, $export);
    }

    /**
     * @param array<int, array{file: string, file_name: string, mime: string}> $files
     * @return array{file: string, file_name: string, mime: string}
     */
    private function findAttachment(array $files, string $file_name): array
    {
        foreach ($files as $file) {
            if ($file['file_name'] === $file_name) {
                return $file;
            }
        }

        $this->fail("Attachment {$file_name} was not found.");
    }

    private function loadXlsx(string $xlsx): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'report_xlsx_test_');

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

    private function company(): Company
    {
        $company = new Company();
        $company->settings = CompanySettings::defaults();

        return $company;
    }

    private function setBaseExportProperty(BaseExport $export, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(BaseExport::class);
        $property_reflection = $reflection->getProperty($property);
        $property_reflection->setAccessible(true);
        $property_reflection->setValue($export, $value);
    }
}