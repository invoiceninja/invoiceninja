<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Recurring;

use App\Models\RecurringInvoice;
use App\Services\Recurring\CreateRecurringInvitations;
use Illuminate\Support\Carbon;

class RecurringService
{
    protected $recurring_entity;

    public function __construct($recurring_entity)
    {
        $this->recurring_entity = $recurring_entity;
    }

    //set schedules - update next_send_dates
    
    /**
     * Stops a recurring invoice 
     * 
     * @return $this RecurringService object
     */
    public function stop()
    {
    	$this->status_id = RecurringInvoice::STATUS_PAUSED;

        return $this;
    }

    public function createInvitations()
    {
        $this->recurring_entity = (new CreateRecurringInvitations($this->recurring_entity))->run();

        return $this;
    }

    public function start()
    {
    	//make sure next_send_date is either now or in the future else return.
    	if(Carbon::parse($this->recurring_entity->next_send_date)->lt(now()))
    		return $this;

    	$this->recurring_entity->status_id = RecurringInvoice::STATUS_ACTIVE;

    	return $this;

    }

    public function save()
    {
    	$this->recurring_entity->save();

    	return $this->recurring_entity;
    }
}
