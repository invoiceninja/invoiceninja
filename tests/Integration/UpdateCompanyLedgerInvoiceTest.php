<?php

namespace Tests\Integration;

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Jobs\Invoice\MarkPaid;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Jobs\Company\UpdateCompanyLedgerWithInvoice
 */
class UpdateCompanyLedgerInvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testUpdatedInvoiceEventFires()
    {

        $this->invoice->status_id = Invoice::STATUS_PAID;
        $this->invoice->save();

    //    $this->expectsEvents(InvoiceWasUpdated::class);
            $activities = Activity::whereInvoiceId($this->invoice->id)->get();

            $this->assertEquals(count($activities), 1);

    }


}