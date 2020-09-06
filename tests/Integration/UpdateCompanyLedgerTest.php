<?php

namespace Tests\Integration;

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Invoice\MarkInvoicePaid;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/** @test*/
class UpdateCompanyLedgerTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    /**
     * @test
     */
    public function testPaymentIsPresentInLedger()
    {
        $invoice = $this->invoice->service()->markPaid()->save();

        $ledger = CompanyLedger::whereClientId($invoice->client_id)
                                ->whereCompanyId($invoice->company_id)
                                ->orderBy('id', 'DESC')
                                ->first();

        $payment = $ledger->adjustment * -1;
        $this->assertEquals($invoice->amount, $payment);
    }

    /**
     * @test
     */
    public function testInvoiceIsPresentInLedger()
    {

        $invoice = $this->invoice->service()->markPaid()->save();

        $this->assertGreaterThan(0, $invoice->company_ledger()->count());
    }
}
