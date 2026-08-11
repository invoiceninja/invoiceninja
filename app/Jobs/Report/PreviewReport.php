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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\User;
use App\Services\Download\ProtectedZipDownloadStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PreviewReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CSV_MIME = 'text/csv';

    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const PDF_MIME = 'application/pdf';

    private const BROWSER_POLL_TIMEOUT_SECONDS = 30;

    public $tries = 1;

    public function __construct(
        protected Company $company,
        protected array $request,
        private string $report_class,
        protected string $hash,
        protected string $file_name,
        protected ?User $user = null,
    ) {}

    public function handle(): void
    {
        $started_at = microtime(true);

        MultiDB::setDb($this->company->db);

        $request = $this->preparePreviewRequest();

        /** @var \App\Services\Report\ProfitLoss|\App\Export\CSV\BaseExport $export */
        $export = new $this->report_class($this->company, $request);

        if ($export instanceof \App\Export\CSV\BaseExport) {
            if ($export->isGroupByActive()) {
                if (isset($request['output']) && $request['output'] == 'json') {
                    $report = $export->groupedReturnJson();
                } else {
                    $report = base64_encode($export->groupedRun());
                }
            } elseif (isset($request['output']) && $request['output'] == 'json') {
                $report = $export->returnJson();
            } elseif (! empty($request['template_id'])) {
                $builder = $export->init();
                $report = $export->exportTemplate($builder, $request['template_id']);
                $report = base64_encode($report);
            } else {
                $report = base64_encode($export->run());
            }
        } else {
            $report = base64_encode($export->run());
        }

        Cache::put($this->hash, $report, 60 * 60);

        if (! $this->user || ($request['output'] ?? null) === 'json') {
            return;
        }

        if (! $this->shouldOfferProtectedDownload($started_at)) {
            return;
        }

        try {
            $files = [[
                'file' => $report,
                'file_name' => $this->file_name,
                'mime' => $this->resolveMimeType($this->file_name),
            ]];

            $dateformat = str_replace("/", "-", $this->company->date_format());
            $datetime = now()->setTimezone($this->company->timezone()->name)->format($dateformat.'-H:i:s');
            $archive_name = str_replace(".csv", "", $this->file_name).'_'.$datetime.'.zip';

            app(ProtectedZipDownloadStore::class)->store($files, $archive_name, $this->company, $this->user);
        } catch (\Throwable $th) {
            nlog('EXCEPTION:: PreviewReport:: could not upload report for '.$th->getMessage());
        }
    }

    private function shouldOfferProtectedDownload(float $started_at): bool
    {
        return (microtime(true) - $started_at) > self::BROWSER_POLL_TIMEOUT_SECONDS;
    }

    private function preparePreviewRequest(): array
    {
        $request = $this->request;

        $request['document_email_attachment'] = false;
        $request['pdf_email_attachment'] = false;

        return $request;
    }

    private function resolveMimeType(string $file_name): string
    {
        if ($this->isXlsxFileName($file_name)) {
            return self::XLSX_MIME;
        }

        if (Str::endsWith(Str::lower($file_name), '.pdf')) {
            return self::PDF_MIME;
        }

        return self::CSV_MIME;
    }

    private function isXlsxFileName(string $file_name): bool
    {
        return Str::endsWith(Str::lower($file_name), '.xlsx');
    }

    public function failed(?\Throwable $exception): void
    {
        if ($exception) {
            nlog('EXCEPTION:: PreviewReport:: could not preview report for '.$exception->getMessage());
        }
    }
}
