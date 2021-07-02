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

namespace App\Jobs\Util;

use App\DataMapper\InvoiceItem;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Jobs\Entity\EmailEntity;
use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesReminders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesReminders, MakesDates;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        if (! config('ninja.db.multi_db_enabled')) {
            $this->processReminders();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);
                $this->processReminders();
            }
        }
    }

    private function processReminders()
    {
        nlog("Sending invoice reminders " . now()->format('Y-m-d h:i:s'));

        Invoice::where('next_send_date', '<=', now()->toDateTimeString())
                 ->whereNull('deleted_at')
                 ->where('is_deleted', 0)
                 ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                 ->where('balance', '>', 0)
                 ->whereHas('client', function ($query) {
                     $query->where('is_deleted',0)
                           ->where('deleted_at', NULL);
                 })
                 ->with('invitations')->cursor()->each(function ($invoice) {

            if ($invoice->isPayable()) {
                $reminder_template = $invoice->calculateTemplate('invoice');
                $invoice->service()->touchReminder($reminder_template)->save();
                
                $invoice = $this->calcLateFee($invoice, $reminder_template);

                $invoice->invitations->each(function ($invitation) use ($invoice, $reminder_template) {
                    EmailEntity::dispatch($invitation, $invitation->company, $reminder_template);
                    nlog("Firing reminder email for invoice {$invoice->number}");
                });

                if ($invoice->invitations->count() > 0) {
                    event(new InvoiceWasEmailed($invoice->invitations->first(), $invoice->company, Ninja::eventVars(), $reminder_template));
                }

                $invoice->service()->setReminder()->save();
                
            } else {
                $invoice->next_send_date = null;
                $invoice->save();
            }

        });
    }


    /**
     * Calculates the late if - if any - and rebuilds the invoice
     *
     * @param  Invoice $invoice
     * @param  string $template
     * @return Invoice
     */
    private function calcLateFee($invoice, $template) :Invoice
    {
        $late_fee_amount = 0;
        $late_fee_percent = 0;

        switch ($template) {
            case 'reminder1':
                $late_fee_amount = $invoice->client->getSetting('late_fee_amount1');
                $late_fee_percent = $invoice->client->getSetting('late_fee_percent1');
                break;
            case 'reminder2':
                $late_fee_amount = $invoice->client->getSetting('late_fee_amount2');
                $late_fee_percent = $invoice->client->getSetting('late_fee_percent2');
                break;
            case 'reminder3':
                $late_fee_amount = $invoice->client->getSetting('late_fee_amount3');
                $late_fee_percent = $invoice->client->getSetting('late_fee_percent3');
                break;
            case 'endless_reminder':
                $late_fee_amount = $invoice->client->getSetting('late_fee_endless_amount');
                $late_fee_percent = $invoice->client->getSetting('late_fee_endless_percent');
                break;
            default:
                $late_fee_amount = 0;
                $late_fee_percent = 0;
                break;
        }

        return $this->setLateFee($invoice, $late_fee_amount, $late_fee_percent);
    }

    /**
     * Applies the late fee to the invoice line items
     *
     * @param Invoice $invoice
     * @param float $amount  The fee amount
     * @param float $percent The fee percentage amount
     *
     * @return Invoice
     */
    private function setLateFee($invoice, $amount, $percent) :Invoice
    {
        $temp_invoice_balance = $invoice->balance;

        if ($amount <= 0 && $percent <= 0) {
            return $invoice;
        }

        $fee = $amount;

        if ($invoice->partial > 0) {
            $fee += round($invoice->partial * $percent / 100, 2);
        } else {
            $fee += round($invoice->balance * $percent / 100, 2);
        }

        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = '5';
        $invoice_item->product_key = trans('texts.fee');
        $invoice_item->notes = ctrans('texts.late_fee_added', ['date' => $this->translateDate(now()->startOfDay(), $invoice->client->date_format(), $invoice->client->locale())]);
        $invoice_item->quantity = 1;
        $invoice_item->cost = $fee;

        $invoice_items = $invoice->line_items;
        $invoice_items[] = $invoice_item;

        $invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $invoice = $invoice->calc()->getInvoice();

        $invoice->client->service()->updateBalance($this->invoice->balance - $temp_invoice_balance)->save();
        $invoice->ledger()->updateInvoiceBalance($this->invoice->balance - $temp_invoice_balance, "Late Fee Adjustment for invoice {$this->invoice->number}");

        return $invoice;
    }
}
