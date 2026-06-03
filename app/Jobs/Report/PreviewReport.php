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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PreviewReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;
    /**
     * Create a new job instance
     */
    public function __construct(protected Company $company, protected array $request, private string $report_class, protected string $hash) {}

    public function handle()
    {
        // nlog("PreviewReport:: handle()");
        // $start = microtime(true);
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
            } elseif (!empty($request['template_id'])) {
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
        // nlog("PreviewReport:: handle() completed in " . (microtime(true) - $start) . " seconds");
    }

    private function preparePreviewRequest(): array
    {
        $request = $this->request;

        $request['document_email_attachment'] = false;
        $request['pdf_email_attachment'] = false;

        return $request;
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception)
    {
        if ($exception) {
            nlog("EXCEPTION:: PreviewReport:: could not preview report for " . $exception->getMessage());
        }
    }
}
