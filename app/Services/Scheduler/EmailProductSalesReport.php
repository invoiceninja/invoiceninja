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

use App\DataMapper\Schedule\EmailStatement;
use App\Models\Client;
use App\Models\Scheduler;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;

class EmailProductSalesReport
{
    use MakesHash;
    use MakesDates;

    private Client $client;

    private bool $multiple_clients = false;

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {
        $query = Client::query()
                ->where('company_id', $this->scheduler->company_id)
                ->where('is_deleted', 0);

        //Email only the selected clients
        
        $start_end_dates = $this->calculateStartAndEndDates();

        if (count($this->scheduler->parameters['clients']) >= 1) {
            $query->whereIn('id', $this->transformKeys($this->scheduler->parameters['clients']));
        }
        
        
        $data = [
            'start_date' => $start_end_dates[0],
            'end_date' => $start_end_dates[1],
            'date_range' => 'custom',
            'client_id' =>
        ];

        $export = new ProductSalesExport($this->scheduler->company, $data);


        //calculate next run dates;
        $this->scheduler->calculateNextRun();
        
    }


    
   

}
