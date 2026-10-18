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

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Jobs\Cron\RecurringInvoicesCron;
use App\Factory\InvoiceToRecurringInvoiceFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Jobs\Cron\RecurringInvoicesCron
 */
class RecurringInvoicesCronTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        RecurringInvoice::all()->each(function ($ri) {
            $ri->forceDelete();
        });

        $this->makeTestData();
    }

    public function testCountCorrectNumberOfRecurringInvoicesDue()
    {
        //spin up 5 valid and 1 invalid recurring invoices
        $recurring_invoices = RecurringInvoice::where('next_send_date', '<=', Carbon::now()->addMinutes(30))->get();

        $recurring_all = RecurringInvoice::all();

        $this->assertEquals(5, $recurring_invoices->count());

        $this->assertEquals(7, $recurring_all->count());
    }

    /**
     * Proves the early `return;` inside the RecurringInvoicesCron `each()` closure
     * only skips the single blocked recurring invoice, NOT every subsequent one.
     *
     * The FIRST recurring invoice in the cursor sequence is set up to trip
     * stop_on_unpaid_recurring (it has an unpaid, sent invoice with a balance).
     * Every subsequent recurring invoice only has a fully paid invoice, so it
     * must still be processed and generate a fresh invoice.
     */
    public function testEarlyReturnOnlySkipsTheBlockedRecurringInvoice()
    {
        /* Clean slate - remove the recurring invoices scaffolded by MockAccountData */
        RecurringInvoice::query()->cursor()->each(function ($ri) {
            $ri->forceDelete();
        });

        /* Enable the unpaid guard for the whole company */
        $this->company->stop_on_unpaid_recurring = true;
        $this->company->save();

        /* Keep SendRecurring on its cheap, no-email branch so the test stays focused */
        $client_settings = $this->client->settings;
        $client_settings->auto_email_invoice = false;
        $this->client->settings = $client_settings;
        $this->client->save();

        /* Build 4 active, due recurring invoices. They are created in order, so the
           first one created is first out of the unordered cursor. */
        $recurrings = collect();

        for ($i = 0; $i < 4; $i++) {
            $ri = InvoiceToRecurringInvoiceFactory::create($this->invoice);
            $ri->user_id = $this->user->id;
            $ri->company_id = $this->company->id;
            $ri->client_id = $this->client->id;
            $ri->status_id = RecurringInvoice::STATUS_ACTIVE;
            $ri->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
            $ri->remaining_cycles = 5;
            $ri->next_send_date = now()->subMinute();
            $ri->next_send_date_client = now()->subMinute();
            $ri->last_sent_date = now()->subMonth();
            $ri->save();

            $ri->number = $this->getNextRecurringInvoiceNumber($this->client, $this->invoice);
            $ri->save();

            $recurrings->push($ri);
        }

        $blocked = $recurrings->first();
        $subsequent = $recurrings->slice(1);

        /* FIRST recurring invoice -> an unpaid SENT invoice with a balance triggers the guard */
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'recurring_id' => $blocked->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 10,
            'amount' => 10,
            'is_deleted' => false,
        ]);

        /* SUBSEQUENT recurring invoices -> a fully PAID invoice, so the guard does NOT trip */
        $subsequent->each(function ($ri) {
            Invoice::factory()->create([
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'user_id' => $this->user->id,
                'recurring_id' => $ri->id,
                'status_id' => Invoice::STATUS_PAID,
                'balance' => 0,
                'amount' => 10,
                'is_deleted' => false,
            ]);
        });

        (new RecurringInvoicesCron())->handle();

        /* The blocked recurring invoice must have been skipped:
           - no new invoice generated (still just the seeded unpaid one)
           - next_send_date NOT advanced (still in the past / due) */
        $blocked->refresh();
        $this->assertEquals(1, $blocked->invoices()->count());
        $this->assertTrue(Carbon::parse($blocked->next_send_date)->isPast());

        /* Every subsequent recurring invoice must have been processed despite the
           earlier early-return:
           - a fresh invoice generated (seeded paid one + 1 == 2)
           - next_send_date advanced into the future */
        $subsequent->each(function ($ri) {
            $ri->refresh();
            $this->assertEquals(2, $ri->invoices()->count(), "Recurring invoice {$ri->id} should have generated a new invoice");
            $this->assertTrue(Carbon::parse($ri->next_send_date)->isFuture(), "Recurring invoice {$ri->id} should have advanced its next_send_date");
        });
    }
}
