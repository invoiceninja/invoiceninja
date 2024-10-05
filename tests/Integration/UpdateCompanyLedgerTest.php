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

namespace Tests\Integration;

use App\Models\CompanyLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/** */
class UpdateCompanyLedgerTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    /**
     * 
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
     * 
     */
    public function testInvoiceIsPresentInLedger()
    {
        $invoice = $this->invoice->service()->markPaid()->save();

        $this->assertGreaterThan(0, $invoice->company_ledger()->count());
    }
}
