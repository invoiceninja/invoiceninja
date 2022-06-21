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

namespace Tests\Unit;

use App\Factory\RecurringInvoiceFactory;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers \App\Models\RecurringInvoice
 */
class RecurringDatesTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testRecurringDatesDraftInvoice()
    {
        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;
        $recurring_invoice->save();

        $recurring_invoice->calc()->getInvoice();

        $this->assertEquals(0, count($recurring_invoice->recurringDates()));
    }

    public function testRecurringDatesPendingInvoice()
    {
        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;

        $recurring_invoice->status_id = RecurringInvoice::STATUS_PENDING;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = '5';
        $recurring_invoice->next_send_date = now();

        $recurring_invoice->save();

        $recurring_invoice->calc()->getInvoice();

        $this->assertEquals(5, count($recurring_invoice->recurringDates()));
    }

    public function testRecurringDatesPendingInvoiceWithNoDueDate()
    {
        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;

        $recurring_invoice->status_id = RecurringInvoice::STATUS_PENDING;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = null;
        $recurring_invoice->next_send_date = now();

        $recurring_invoice->save();

        $recurring_invoice->calc()->getInvoice();

        $this->assertEquals(5, count($recurring_invoice->recurringDates()));
    }

    public function testCompareDatesLogic()
    {
        $date = now()->startOfDay()->format('Y-m-d');

        $this->assertTrue(Carbon::parse($date)->lte(now()->startOfDay()));
    }
}
