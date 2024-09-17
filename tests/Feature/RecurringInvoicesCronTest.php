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

use App\Models\RecurringInvoice;
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
}
