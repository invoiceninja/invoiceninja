<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Report;

use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class PreviewReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance
     */
    public function __construct(protected Company $company, protected array $request, private string $report_class, protected string $hash)
    {
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);

        /** @var \App\Export\CSV\CreditExport $export */
        $export = new $this->report_class($this->company, $this->request);
        $report = $export->returnJson();

        // nlog($report);
        Cache::put($this->hash, $report, 60 * 60);
    }

    public function middleware()
    {
        return [new WithoutOverlapping("report-{$this->company->company_key}")];
    }
}
