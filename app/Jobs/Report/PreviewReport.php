<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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
    public function __construct(protected Company $company, protected array $request, private string $report_class, protected string $hash)
    {
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);

        /** @var \App\Export\CSV\BaseExport $export */
        $export = new $this->report_class($this->company, $this->request);

        if (isset($this->request['output']) && $this->request['output'] == 'json') {
            $report = $export->returnJson();
        } 
        elseif(!empty($this->request['template_id'])){
            $builder = $export->init();
            $report = $export->exportTemplate($builder, $this->request['template_id']);
            $report = base64_encode($report);
        }
        else {
            $report = base64_encode($export->run());
        }

        Cache::put($this->hash, $report, 60 * 60);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception)
    {
        if($exception) {
            nlog("EXCEPTION:: PreviewReport:: could not preview report for " . $exception->getMessage());
        }
    }
}
