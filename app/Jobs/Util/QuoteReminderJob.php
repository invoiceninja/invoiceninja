<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Util;

use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Jobs\Entity\EmailEntity;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\MakesReminders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\Quote\QuoteReminderWasEmailed;

class QuoteReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesReminders;
    use MakesDates;

    public $tries = 1;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        set_time_limit(0);

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            nrlog("Sending quote reminders on ".now()->format('Y-m-d h:i:s'));

            Quote::query()
                 ->where('is_deleted', 0)
                 ->whereIn('status_id', [Invoice::STATUS_SENT])
                 ->whereNull('deleted_at')
                 ->where('next_send_date', '<=', now()->toDateTimeString())
                 ->whereHas('client', function ($query) {
                     $query->where('is_deleted', 0)
                           ->where('deleted_at', null);
                 })
                 ->whereHas('company', function ($query) {
                     $query->where('is_disabled', 0);
                 })
                 ->with('invitations')->chunk(50, function ($quotes) {
                     foreach ($quotes as $quote) {
                         $this->sendReminderForQuote($quote);
                     }

                     sleep(1);
                 });
        } else {
            //multiDB environment, need to

            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                nrlog("Sending quote reminders on db {$db} ".now()->format('Y-m-d h:i:s'));

                Quote::query()
                     ->where('is_deleted', 0)
                     ->whereIn('status_id', [Invoice::STATUS_SENT])
                     ->whereNull('deleted_at')
                     ->where('next_send_date', '<=', now()->toDateTimeString())
                     ->whereHas('client', function ($query) {
                         $query->where('is_deleted', 0)
                               ->where('deleted_at', null);
                     })
                     ->whereHas('company', function ($query) {
                         $query->where('is_disabled', 0);
                     })
                     ->with('invitations')->chunk(50, function ($quotes) {

                         foreach ($quotes as $quote) {
                             $this->sendReminderForQuote($quote);
                         }

                         sleep(1);
                     });
            }
        }
    }

    private function sendReminderForQuote(Quote $quote)
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($quote->client->getMergedSettings()));
        App::setLocale($quote->client->locale());

        if ($quote->isPayable()) {
            //Attempts to prevent duplicates from sending
            if ($quote->reminder_last_sent && Carbon::parse($quote->reminder_last_sent)->startOfDay()->eq(now()->startOfDay())) {
                nrlog("caught a duplicate reminder for quote {$quote->number}");
                return;
            }

            $reminder_template = $quote->calculateTemplate('invoice');
            nrlog("#{$quote->number} => reminder template = {$reminder_template}");
            $quote->service()->touchReminder($reminder_template)->save();
            $fees = $this->calcLateFee($quote, $reminder_template);

            if($quote->isLocked()) {
                return $this->addFeeToNewQuote($quote, $reminder_template, $fees);
            }

            $quote = $this->setLateFee($quote, $fees[0], $fees[1]);

            //20-04-2022 fixes for endless reminders - generic template naming was wrong
            $enabled_reminder = 'enable_'.$reminder_template;
            if ($reminder_template == 'endless_reminder') {
                $enabled_reminder = 'enable_reminder_endless';
            }

            if (in_array($reminder_template, ['reminder1', 'reminder2', 'reminder3', 'reminder_endless', 'endless_reminder']) &&
        $quote->client->getSetting($enabled_reminder) &&
        $quote->client->getSetting('send_reminders') &&
        (Ninja::isSelfHost() || $quote->company->account->isPaidHostedClient())) {
                $quote->invitations->each(function ($invitation) use ($quote, $reminder_template) {
                    if ($invitation->contact && !$invitation->contact->trashed() && $invitation->contact->email) {
                        EmailEntity::dispatch($invitation, $invitation->company, $reminder_template);
                        nrlog("Firing reminder email for invoice {$quote->number} - {$reminder_template}");
                        $quote->entityEmailEvent($invitation, $reminder_template);
                        $quote->sendEvent(Webhook::EVENT_REMIND_INVOICE, "client");
                    }
                });
            }
            $quote->service()->setReminder()->save();
        } else {
            $quote->next_send_date = null;
            $quote->save();
        }
    }

    private function addFeeToNewQuote(Quote $over_due_quote, string $reminder_template, array $fees)
    {

        $amount = $fees[0];
        $percent = $fees[1];

        $quote = false;

        //2024-06-07 this early return prevented any reminders from sending for users who enabled lock_invoices.
        if ($amount > 0 || $percent > 0) {
            // return;

            $fee = $amount;

            if ($over_due_quote->partial > 0) {
                $fee += round($over_due_quote->partial * $percent / 100, 2);
            } else {
                $fee += round($over_due_quote->balance * $percent / 100, 2);
            }

            /** @var \App\Models\Invoice $quote */
            $quote = InvoiceFactory::create($over_due_quote->company_id, $over_due_quote->user_id);
            $quote->client_id = $over_due_quote->client_id;
            $quote->date = now()->format('Y-m-d');
            $quote->due_date = now()->format('Y-m-d');

            $quote_item = new InvoiceItem();
            $quote_item->type_id = '5';
            $quote_item->product_key = trans('texts.fee');
            $quote_item->notes = ctrans('texts.late_fee_added_locked_invoice', ['invoice' => $over_due_quote->number, 'date' => $this->translateDate(now()->startOfDay(), $over_due_invoice->client->date_format(), $over_due_invoice->client->locale())]);
            $quote_item->quantity = 1;
            $quote_item->cost = $fee;

            $quote_items = [];
            $quote_items[] = $quote_item;

            $quote->line_items = $quote_items;

            /**Refresh Invoice values*/
            $quote = $quote->calc()->getInvoice();
            $quote->service()
                    ->createInvitations()
                    ->applyNumber()
                    ->markSent()
                    ->save();
        }

        if(!$quote) {
            $quote = $over_due_quote;
        }

        $enabled_reminder = 'enable_'.$reminder_template;
        // if ($reminder_template == 'endless_reminder') {
        //     $enabled_reminder = 'enable_reminder_endless';
        // }

        if (in_array($reminder_template, ['reminder1', 'reminder2', 'reminder3', 'reminder_endless', 'endless_reminder']) &&
                $quote->client->getSetting($enabled_reminder) &&
                $quote->client->getSetting('send_reminders') &&
                (Ninja::isSelfHost() || $quote->company->account->isPaidHostedClient())) {
            $quote->invitations->each(function ($invitation) use ($quote, $reminder_template) {
                if ($invitation->contact && !$invitation->contact->trashed() && $invitation->contact->email) {
                    EmailEntity::dispatch($invitation, $invitation->company, $reminder_template);
                    nrlog("Firing reminder email for qipte {$quote->number} - {$reminder_template}");
                    event(new QuoteReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $reminder_template));
                    $quote->sendEvent(Webhook::EVENT_REMIND_QUOTE, "client"); 
                }
            });
        }

        $quote->service()->setReminder()->save();

    }

    /**
     * Calculates the late if - if any - and rebuilds the invoice
     *
     * @param  Invoice $quote
     * @param  string $template
     * @return array
     */
    private function calcLateFee($quote, $template): array
    {
        $late_fee_amount = 0;
        $late_fee_percent = 0;

        switch ($template) {
            case 'reminder1':
                $late_fee_amount = $quote->client->getSetting('late_fee_amount1');
                $late_fee_percent = $quote->client->getSetting('late_fee_percent1');
                break;
            case 'reminder2':
                $late_fee_amount = $quote->client->getSetting('late_fee_amount2');
                $late_fee_percent = $quote->client->getSetting('late_fee_percent2');
                break;
            case 'reminder3':
                $late_fee_amount = $quote->client->getSetting('late_fee_amount3');
                $late_fee_percent = $quote->client->getSetting('late_fee_percent3');
                break;
            case 'endless_reminder':
                $late_fee_amount = $quote->client->getSetting('late_fee_endless_amount');
                $late_fee_percent = $quote->client->getSetting('late_fee_endless_percent');
                break;
            default:
                $late_fee_amount = 0;
                $late_fee_percent = 0;
                break;
        }

        return [$late_fee_amount, $late_fee_percent];
    }

    /**
     * Applies the late fee to the invoice line items
     *
     * @param Invoice $quote
     * @param float $amount  The fee amount
     * @param float $percent The fee percentage amount
     *
     * @return Invoice
     */
    private function setLateFee($quote, $amount, $percent): Invoice
    {

        $temp_invoice_balance = $quote->balance;

        if ($amount <= 0 && $percent <= 0) {
            return $quote;
        }

        $fee = $amount;

        if ($quote->partial > 0) {
            $fee += round($quote->partial * $percent / 100, 2);
        } else {
            $fee += round($quote->balance * $percent / 100, 2);
        }

        $quote_item = new InvoiceItem();
        $quote_item->type_id = '5';
        $quote_item->product_key = trans('texts.fee');
        $quote_item->notes = ctrans('texts.late_fee_added', ['date' => $this->translateDate(now()->startOfDay(), $quote->client->date_format(), $quote->client->locale())]);
        $quote_item->quantity = 1;
        $quote_item->cost = $fee;

        $quote_items = $quote->line_items;
        $quote_items[] = $quote_item;

        $quote->line_items = $quote_items;

        /**Refresh Invoice values*/
        $quote = $quote->calc()->getInvoice();

        $quote->ledger()->updateInvoiceBalance($quote->balance - $temp_invoice_balance, "Late Fee Adjustment for invoice {$quote->number}");
        $quote->client->service()->calculateBalance();

        return $quote;
    }
}
