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
use App\DataMapper\Schedule\EmailStatement;

class EmailProductSalesReport
{
    use MakesHash;
    use MakesDates;

    private Client $client;

    private bool $multiple_clients = false;

    private string $file_name = 'product_sales.csv';

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {
        
        $start_end_dates = $this->calculateStartAndEndDates();
        $data = [];

        if (count($this->scheduler->parameters['clients']) >= 1) {            
            $data['clients'] = $this->transformKeys($this->scheduler->parameters['clients']);
        }
        
        $data = [
            'start_date' => $start_end_dates[0],
            'end_date' => $start_end_dates[1],
            'date_range' => 'custom',
            'client_id' => null
        ];

        $export = (new ProductSalesExport($this->scheduler->company, $data));
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


    
   

}
