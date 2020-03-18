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

/** @test
/** @covers App\Services\Ledger\LedgerService */

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
     * @covers  App\Jobs\Company\UpdateCompanyLedgerWithPayment
     */
    public function testPaymentIsPresentInLedger()
    {
        $invoice = $this->invoice->service()->markPaid()->save();

        $ledger = CompanyLedger::whereClientId($invoice->client_id)
                                ->whereCompanyId($invoice->company_id)
                                ->orderBy('id', 'DESC')
                                ->first();

        $payment = $ledger->adjustment * - 1;
        $this->assertEquals($invoice->amount, $payment);
    }

    /**
     * @test
     * @covers  App\Jobs\Company\UpdateCompanyLedgerWithInvoice
     */
    public function testInvoiceIsPresentInLedger()
    {
        //$this->invoice->save();

        $ledger = CompanyLedger::whereCompanyLedgerableId($this->invoice->id)
                                    ->whereCompanyLedgerableType(Invoice::class)
                                    ->whereCompanyId($this->invoice->company_id)
                                    ->get();

        $this->assertGreaterThan(1, count($ledger));
    }
}
