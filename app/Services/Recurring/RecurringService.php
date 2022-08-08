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

namespace App\Services\Recurring;

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Jobs\Util\UnlinkFile;
use App\Models\RecurringInvoice;
use App\Services\Recurring\GetInvoicePdf;
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
        if($this->recurring_entity->status_id < RecurringInvoice::STATUS_PAUSED)
            $this->recurring_entity->status_id = RecurringInvoice::STATUS_PAUSED;

        return $this;
    }

    public function createInvitations()
    {
        $this->recurring_entity = (new CreateRecurringInvitations($this->recurring_entity))->run();

        return $this;
    }

    public function start()
    {

        if ($this->recurring_entity->remaining_cycles == 0) {
            return $this;
        }

        $this->setStatus(RecurringInvoice::STATUS_ACTIVE);
        
        return $this;
    }

    public function setStatus($status)
    {
        $this->recurring_entity->status_id = $status;

        return $this;
    }

    /**
     * Applies the invoice number.
     * @return $this InvoiceService object
     */
    public function applyNumber()
    {
        $this->recurring_entity = (new ApplyNumber($this->recurring_entity->client, $this->recurring_entity))->run();

        return $this;
    }

    public function getInvoicePdf($contact = null)
    {
        return (new GetInvoicePdf($this->recurring_entity, $contact))->run();
    }

    public function deletePdf()
    {

        $this->recurring_entity->invitations->each(function ($invitation){

        UnlinkFile::dispatchNow(config('filesystems.default'), $this->recurring_entity->client->recurring_invoice_filepath($invitation) . $this->recurring_entity->numberFormatter().'.pdf');
        
        });


        return $this;
    }
    
    public function triggeredActions($request)
    {

        if ($request->has('start') && $request->input('start') == 'true') {
            $this->start();
        }

        if ($request->has('stop') && $request->input('stop') == 'true') {
            $this->stop();
        }
        
        if ($request->has('send_now') && $request->input('send_now') == 'true' && $this->recurring_entity->invoices()->count() == 0) {
            $this->sendNow();
        }

        if(isset($this->recurring_entity->client))
        {
            $offset = $this->recurring_entity->client->timezone_offset();
            $this->recurring_entity->next_send_date = Carbon::parse($this->recurring_entity->next_send_date_client)->startOfDay()->addSeconds($offset);
        }

        return $this;
    }

    public function sendNow()
    {
    
        if($this->recurring_entity instanceof RecurringInvoice && $this->recurring_entity->status_id == RecurringInvoice::STATUS_DRAFT){
            $this->start()->save();
            SendRecurring::dispatch($this->recurring_entity, $this->recurring_entity->company->db); 
        }

        return $this->recurring_entity;

    }

    public function fillDefaults()
    {

        return $this;
    }
    
    public function save()
    {
        $this->recurring_entity->saveQuietly();

        return $this->recurring_entity;
    }
}
