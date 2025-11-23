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

use App\Models\User;
use App\Models\Company;
use App\Libraries\MultiDB;
use App\Mail\DownloadReport;
use Illuminate\Bus\Queueable;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Services\Report\ARDetailReport;
use App\Services\Report\ARSummaryReport;
use App\Services\Report\ClientBalanceReport;
use App\Services\Report\ClientSalesReport;
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

    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Company $company, protected array $request, protected string $report_class, protected string $file_name)
    {
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);
        $export = new $this->report_class($this->company, $this->request);
        $csv_file = $export->run();
        $csv = base64_encode($csv_file);
        $mime = 'text/csv';

        $file_name = $this->file_name;

        $size_mb = round(strlen($csv) / (1024 * 1024), 2); // Size in MB
        
        // If the file is greater than 5MB, we need to zip it to ensure it does not break attachment size limits
        if($size_mb > 5){

            $zipFile = new \PhpZip\ZipFile();
            $file_name = basename($file_name).'.zip';

            try {
                $zipFile->addFromString($this->file_name, $csv_file);
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }

            $csv = base64_encode($zipFile->outputAsString());
            $mime = 'application/zip';

        }

        $files = [];
        $files[] = ['file' => $csv, 'file_name' => "{$file_name}", 'mime' => $mime];

        if (in_array(get_class($export), [ARDetailReport::class, ARSummaryReport::class, ClientBalanceReport::class, ClientSalesReport::class, TaxSummaryReport::class])) {
            $pdf = base64_encode($export->getPdf());
            $files[] = ['file' => $pdf, 'file_name' => str_replace(".csv", ".pdf", $this->file_name), 'mime' => 'application/pdf'];
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
            nlog("EXCEPTION:: SendToAdmin:: could not email report for" . $th->getMessage());
        }

    }

    public function failed(?\Throwable $exception = null)
    {
        if($exception) {
            nlog("EXCEPTION:: SendToAdmin:: could not email report for" . $exception->getMessage());
        }
    }
}
