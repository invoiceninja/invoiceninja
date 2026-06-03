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

namespace App\Jobs\Report;

use App\Models\User;
use App\Models\Company;
use App\Export\CSV\BaseExport;
use App\Libraries\MultiDB;
use App\Mail\DownloadReport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Services\Report\ARDetailReport;
use App\Services\Report\ARSummaryReport;
use App\Services\Report\ClientBalanceReport;
use App\Services\Report\ClientSalesReport;
use App\Services\Report\CsvToXlsxConverter;
use App\Services\Report\RawRowsXlsxWriter;
use App\Services\Report\TaxSummaryReport;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendToAdmin implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CSV_MIME = 'text/csv';

    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const ZIP_MIME = 'application/zip';

    private const MAX_ATTACHMENT_SIZE_MB = 5;

    private const TYPED_XLSX_REPORTS = [
        \App\Export\CSV\ClientExport::class,
        \App\Export\CSV\ContactExport::class,
        \App\Export\CSV\CreditExport::class,
        \App\Export\CSV\DocumentExport::class,
        \App\Export\CSV\ExpenseExport::class,
        \App\Export\CSV\InvoiceExport::class,
        \App\Export\CSV\InvoiceItemExport::class,
        \App\Export\CSV\LocationExport::class,
        \App\Export\CSV\PaymentExport::class,
        \App\Export\CSV\ProductExport::class,
        \App\Export\CSV\PurchaseOrderExport::class,
        \App\Export\CSV\PurchaseOrderItemExport::class,
        \App\Export\CSV\QuoteExport::class,
        \App\Export\CSV\QuoteItemExport::class,
        \App\Export\CSV\RecurringInvoiceExport::class,
        \App\Export\CSV\RecurringInvoiceItemExport::class,
        \App\Export\CSV\TaskExport::class,
        \App\Export\CSV\VendorExport::class,
    ];

    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Company $company, protected array $request, protected string $report_class, protected string $file_name) {}

    public function handle(): void
    {
        MultiDB::setDb($this->company->db);
        $export = new $this->report_class($this->company, $this->request);

        if ($this->isCsvFileName($this->file_name) && $this->supportsTypedXlsx($export)) {
            $export->captureRawRows();
        }

        $report_file = ($export instanceof BaseExport && $export->isGroupByActive()) ? $export->groupedRun() : $export->run();

        $files = $this->buildReportAttachments($report_file, $export instanceof BaseExport ? $export : null);

        if (in_array(get_class($export), [ARDetailReport::class, ARSummaryReport::class, ClientBalanceReport::class, ClientSalesReport::class, TaxSummaryReport::class])) {
            $pdf = base64_encode($export->getPdf());
            $files[] = ['file' => $pdf, 'file_name' => str_replace('.csv', '.pdf', $this->file_name), 'mime' => 'application/pdf'];
        }

        $user = $this->company->owner();

        if (isset($this->request['user_id'])) {
            $user = User::find($this->request['user_id']) ?? $this->company->owner();
        }

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new DownloadReport($this->company, $files);
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $user;

        try {
            (new NinjaMailerJob($nmo))->handle();
        } catch (\Throwable $th) {
            nlog('EXCEPTION:: SendToAdmin:: could not email report for' . $th->getMessage());
        }

    }

    /**
     * @return array<int, array{file: string, file_name: string, mime: string}>
     */
    private function buildReportAttachments(string $report_file, ?BaseExport $export = null): array
    {
        $files = [
            $this->buildAttachment($report_file, $this->file_name, $this->resolveMimeType($this->file_name)),
        ];

        if ($this->isCsvFileName($this->file_name)) {
            $xlsx_file = $this->buildXlsxFile($report_file, $export);
            $files[] = $this->buildAttachment($xlsx_file, $this->xlsxFileName($this->file_name), self::XLSX_MIME);
        }

        return $files;
    }

    private function buildXlsxFile(string $report_file, ?BaseExport $export): string
    {
        if ($export && $this->supportsTypedXlsx($export) && $export->hasRawRows()) {
            $xlsx_file = app(RawRowsXlsxWriter::class)->convert($export);

            if (is_string($xlsx_file)) {
                return $xlsx_file;
            }
        }

        return app(CsvToXlsxConverter::class)->convert($report_file);
    }

    /**
     * @return array{file: string, file_name: string, mime: string}
     */
    private function buildAttachment(string $contents, string $file_name, string $mime): array
    {
        $encoded_file = base64_encode($contents);
        $size_mb = round(strlen($encoded_file) / (1024 * 1024), 2);

        if ($size_mb <= self::MAX_ATTACHMENT_SIZE_MB) {
            return ['file' => $encoded_file, 'file_name' => $file_name, 'mime' => $mime];
        }

        $zipFile = new \PhpZip\ZipFile();

        try {
            $zipFile->addFromString($file_name, $contents);
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }

        return [
            'file' => base64_encode($zipFile->outputAsString()),
            'file_name' => basename($file_name) . '.zip',
            'mime' => self::ZIP_MIME,
        ];
    }

    private function supportsTypedXlsx(object $export): bool
    {
        return $export instanceof BaseExport
            && in_array(get_class($export), self::TYPED_XLSX_REPORTS, true);
    }

    private function resolveMimeType(string $file_name): string
    {
        if ($this->isXlsxFileName($file_name)) {
            return self::XLSX_MIME;
        }

        return self::CSV_MIME;
    }

    private function isCsvFileName(string $file_name): bool
    {
        return Str::endsWith(Str::lower($file_name), '.csv');
    }

    private function isXlsxFileName(string $file_name): bool
    {
        return Str::endsWith(Str::lower($file_name), '.xlsx');
    }

    private function xlsxFileName(string $file_name): string
    {
        return substr($file_name, 0, -4) . '.xlsx';
    }

    public function failed(?\Throwable $exception = null): void
    {
        if ($exception) {
            nlog('EXCEPTION:: SendToAdmin:: could not email report for' . $exception->getMessage());
        }
    }
}
