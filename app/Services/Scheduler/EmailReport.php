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

namespace App\Services\Scheduler;

use App\Models\Client;
use App\Models\Scheduler;
use App\Mail\DownloadReport;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Export\CSV\ProductSalesExport;
use App\Services\Report\ARDetailReport;
use App\Services\Report\ARSummaryReport;
use App\Services\Report\ClientBalanceReport;
use App\Services\Report\ClientSalesReport;
use App\Services\Report\TaxSummaryReport;
use App\Services\Report\UserSalesReport;

class EmailReport
{
    use MakesHash;
    use MakesDates;

    private Client $client;

    private bool $multiple_clients = false;

    private string $file_name = 'file.csv';

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {
        
        $start_end_dates = $this->calculateStartAndEndDates();
        $data = [];

        $data = [
            'start_date' => $start_end_dates[0],
            'end_date' => $start_end_dates[1],
            'date_range' => 'custom',
            'client_id' => null,
            'report_keys' => []
        ];

        if (count($this->scheduler->parameters['clients']) >= 1) {
            $data['clients'] = $this->transformKeys($this->scheduler->parameters['clients']);
        }
        
        $export = false;

        match($this->scheduler->parameters['report_name'])
        {
            'product_sales_report' => $export = (new ProductSalesExport($this->scheduler->company, $data)),
            'email_ar_detailed_report' => (new ARDetailReport($this->scheduler->company, $data)),
            'email_ar_summary_report' => (new ARSummaryReport($this->scheduler->company, $data)),
            'email_tax_summary_report' => (new TaxSummaryReport($this->scheduler->company, $data)),
            'email_client_balance_report' => (new ClientBalanceReport($this->scheduler->company, $data)),
            'email_client_sales_report' => (new ClientSalesReport($this->scheduler->company, $data)),
            'email_user_sales_report' => (new UserSalesReport($this->scheduler->company, $data)),
            default => $export = false,
        };
        
        if(!$export) {
            $this->cancelSchedule();
            return;
        }

        $csv = $export->run();

        //todo - potentially we send this to more than one user.

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new DownloadReport($this->scheduler->company, $csv, $this->file_name);
        $nmo->company = $this->scheduler->company;
        $nmo->settings = $this->scheduler->company->settings;
        $nmo->to_user = $this->scheduler->user;

        NinjaMailerJob::dispatch($nmo);

        //calculate next run dates;
        $this->scheduler->calculateNextRun();
        
    }

    private function cancelSchedule()
    {
        $this->scheduler->forceDelete();
    }
    
   

}
