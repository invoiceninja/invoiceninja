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

use App\Models\Scheduler;

class SchedulerService
{

    public function __construct(public Scheduler $scheduler) {}

    public function scheduleStatement()
    {
        
        //Is it for one client
        //Is it for all clients
        //Is it for all clients excluding these clients
        
        //Frequency
        
        //show aging
        //show payments
        //paid/unpaid
        
        //When to send? 1st of month
        //End of month
        //This date
        
    }

    public function scheduleReport()
    {
        //Report type
        //same schema as ScheduleStatement
    }

    public function scheduleEntitySend()
    {
        //Entity
        //Entity Id
        //When
    }

    public function projectStatus()
    {
        //Project ID
        //Tasks - task statuses
    }

}