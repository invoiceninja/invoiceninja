<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Scheduler;

use App\Models\Client;
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

class SchedulerService
{
    use MakesHash;

    private string $method;

    public function __construct(public Scheduler $scheduler) {}

    /**
     * Called from the TaskScheduler Cron
     * 
     * @return void 
     */
    public function runTask(): void
    {
        $this->{$this->scheduler->template}();
    }

    private function client_statement()
    {   
        $query = Client::query()
                        ->where('company_id', $this->scheduler->company_id);

        //Email only the selected clients
        if(count($this->scheduler->parameters['clients']) >= 1)
            $query->where('id', $this->transformKeys($this->scheduler->parameters['clients']));
        
        $query->cursor()
            ->each(function ($client){

           //work out the date range 

        });
    }

    // public function scheduleStatement()
    // {
        
    //     //Is it for one client
    //     //Is it for all clients
    //     //Is it for all clients excluding these clients
        
    //     //Frequency
        
    //     //show aging
    //     //show payments
    //     //paid/unpaid
        
    //     //When to send? 1st of month
    //     //End of month
    //     //This date
        
    // }

    // public function scheduleReport()
    // {
    //     //Report type
    //     //same schema as ScheduleStatement
    // }

    // public function scheduleEntitySend()
    // {
    //     //Entity
    //     //Entity Id
    //     //When
    // }

    // public function projectStatus()
    // {
    //     //Project ID
    //     //Tasks - task statuses
    // }

}