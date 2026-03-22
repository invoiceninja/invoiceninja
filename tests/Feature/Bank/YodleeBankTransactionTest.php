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

namespace Tests\Feature\Bank;

use App\Models\BankTransaction;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class YodleeBankTransactionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        if (!config('ninja.yodlee.client_id')) {
            $this->markTestSkipped('Skip test no Yodlee API credentials found');
        }

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testDataMatching1()
    {
        $this->invoice->number = "super-funk-1234";
        $this->invoice->save();

        $bank_transaction = BankTransaction::where('company_id', $this->company->id)->first();
        $bank_transaction->description = "super-funk-1234";
        $bank_transaction->save();

        $this->assertNotNull($this->invoice);
        $this->assertNotNull($bank_transaction);

        $invoices = Invoice::where('company_id', $this->company->id)->get();

        BankTransaction::where('company_id', $this->company->id)
                       ->where('status_id', BankTransaction::STATUS_UNMATCHED)
                       ->cursor()
                       ->each(function ($bt) use ($invoices) {
                           $invoice = $invoices->first(function ($value, $key) use ($bt) {
                               return str_contains($value->number, $bt->description);
                           });

                           if ($invoice) {
                               $bt->invoice_ids = $invoice->hashed_id;
                               $bt->status_id = BankTransaction::STATUS_MATCHED;
                               $bt->save();
                           }
                       });


        $this->assertTrue(BankTransaction::where('invoice_ids', $this->invoice->hashed_id)->exists());
    }


    public function testDataMatching2()
    {
        $this->invoice->number = "super-funk-1234";
        $this->invoice->save();

        $bank_transaction = BankTransaction::where('company_id', $this->company->id)->first();
        $bank_transaction->description = "super-funk-123";
        $bank_transaction->save();

        $this->assertNotNull($this->invoice);
        $this->assertNotNull($bank_transaction);

        $invoices = Invoice::where('company_id', $this->company->id)->get();

        BankTransaction::where('company_id', $this->company->id)
                       ->where('status_id', BankTransaction::STATUS_UNMATCHED)
                       ->cursor()
                       ->each(function ($bt) use ($invoices) {
                           $invoice = $invoices->first(function ($value, $key) use ($bt) {
                               return str_contains($value->number, $bt->description);
                           });

                           if ($invoice) {
                               $bt->invoice_ids = $invoice->hashed_id;
                               $bt->status_id = BankTransaction::STATUS_MATCHED;
                               $bt->save();
                           }
                       });


        $this->assertTrue(BankTransaction::where('invoice_ids', $this->invoice->hashed_id)->exists());
    }
}
