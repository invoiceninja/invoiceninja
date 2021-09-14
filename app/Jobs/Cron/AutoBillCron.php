<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Cron;

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

class AutoBillCron
{
    use Dispatchable;

    public $tries = 1;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {
        set_time_limit(0);

        /* Get all invoices where the send date is less than NOW + 30 minutes() */
        info("Performing Autobilling ".Carbon::now()->format('Y-m-d h:i:s'));

        if (! config('ninja.db.multi_db_enabled')) {

            $auto_bill_partial_invoices = Invoice::whereDate('partial_due_date', '<=', now())
                                        ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                        ->where('auto_bill_enabled', true)
                                        ->where('balance', '>', 0)
                                        ->where('is_deleted', false)
                                        ->with('company');

                                        nlog($auto_bill_partial_invoices->count(). " partial invoices to auto bill");

                                        $auto_bill_partial_invoices->cursor()->each(function ($invoice){
                                            $this->runAutoBiller($invoice, false);
                                        });

            $auto_bill_invoices = Invoice::whereDate('due_date', '<=', now())
                                        ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                        ->where('auto_bill_enabled', true)
                                        ->where('balance', '>', 0)
                                        ->where('is_deleted', false)
                                        ->with('company');

                                        nlog($auto_bill_invoices->count(). " full invoices to auto bill");
                                        
                                        $auto_bill_invoices->cursor()->each(function ($invoice){
                                            $this->runAutoBiller($invoice, false);
                                        });


        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
        
                MultiDB::setDB($db);
                
                $auto_bill_partial_invoices = Invoice::whereDate('partial_due_date', '<=', now())
                                            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                            ->where('auto_bill_enabled', true)
                                            ->where('balance', '>', 0)
                                            ->where('is_deleted', false)
                                            ->with('company');

                                            nlog($auto_bill_partial_invoices->count(). " partial invoices to auto bill db = {$db}");

                                            $auto_bill_partial_invoices->cursor()->each(function ($invoice) use($db){
                                                $this->runAutoBiller($invoice, $db);
                                            });

                $auto_bill_invoices = Invoice::whereDate('due_date', '<=', now())
                                            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                            ->where('auto_bill_enabled', true)
                                            ->where('balance', '>', 0)
                                            ->where('is_deleted', false)
                                            ->with('company');

                                            nlog($auto_bill_invoices->count(). " full invoices to auto bill db = {$db}");

                                            $auto_bill_invoices->cursor()->each(function ($invoice) use($db){
                                                $this->runAutoBiller($invoice, $db);
                                            });

            }
        }
    }

    private function runAutoBiller(Invoice $invoice, $db)
    {
        info("Firing autobill for {$invoice->company_id} - {$invoice->number}");

        try{

            if($db)
                MultiDB::setDB($db);
                
            $invoice->service()->autoBill()->save();

        }
        catch(\Exception $e) {
            nlog("Failed to capture payment for {$invoice->company_id} - {$invoice->number} ->" . $e->getMessage());
        }
    }
}
