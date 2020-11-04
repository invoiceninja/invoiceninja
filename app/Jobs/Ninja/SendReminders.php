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

namespace App\Jobs\Ninja;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        info("Sending reminders ".Carbon::now()->format('Y-m-d h:i:s'));

        if (! config('ninja.db.multi_db_enabled')) {

            $this->sendReminderEmails();


        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) 
            {

                MultiDB::setDB($db);

                $this->sendReminderEmails();
            }

        }

    }


    private function chargeLateFee()
    {

    }

    private function sendReminderEmails()
    {
        $invoices = Invoice::where('is_deleted', 0)
                           ->where('balance', '>', 0)
                           ->whereDate('next_send_date', '<=', now()->startOfDay())
                           ->cursor();

        //we only need invoices that are payable
        $invoices->filter(function ($invoice){

            return $invoice->isPayable();

        })->each(function ($invoice){

                $reminder_template = $invoice->calculateTemplate('invoice');

                if($reminder_template == 'reminder1'){

                }
                elseif($reminder_template == 'reminder2'){

                }
                elseif($reminder_template == 'reminder3'){
                    
                }
                elseif($reminder_template == 'endless_reminder'){

                }

                //@todo

        });
        //iterate through all the reminder emails due today
        //
        //determine which reminder
        //
        //determine late fees
        //
        //send
    }

}