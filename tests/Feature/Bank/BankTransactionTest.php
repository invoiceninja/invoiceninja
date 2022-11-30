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

use App\Factory\BankIntegrationFactory;
use App\Factory\BankTransactionFactory;
use App\Models\BankTransaction;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class BankTransactionTest extends TestCase
{

    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testLinkExpenseToTransaction()
    {

        $data = [];

        $bi = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bi->save();

        $bt = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bt->bank_integration_id = $bi->id;
        $bt->status_id = BankTransaction::STATUS_UNMATCHED;
        $bt->description = 'Fuel';
        $bt->amount = 10;
        $bt->currency_code = $this->client->currency()->code;
        $bt->date = now()->format('Y-m-d');
        $bt->transaction_id = 1234567890;
        $bt->category_id = 10000003;
        $bt->base_type = 'DEBIT';
        $bt->save();

        $data = [];

        $data['transactions'][] = [
            'id' => $bt->hashed_id,
            'expense_id' => $this->expense->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);

        $this->assertEquals($this->expense->refresh()->transaction_id, $bt->id);
        $this->assertEquals($bt->refresh()->expense_id, $this->expense->id);
        $this->assertEquals(BankTransaction::STATUS_CONVERTED, $bt->status_id);

    }


    public function testLinkPaymentToTransaction()
    {

        $data = [];

        $bi = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bi->save();

        $bt = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bt->bank_integration_id = $bi->id;
        $bt->status_id = BankTransaction::STATUS_UNMATCHED;
        $bt->description = 'Fuel';
        $bt->amount = 10;
        $bt->currency_code = $this->client->currency()->code;
        $bt->date = now()->format('Y-m-d');
        $bt->transaction_id = 1234567890;
        $bt->category_id = 10000003;
        $bt->base_type = 'CREDIT';
        $bt->save();

        $data = [];

        $data['transactions'][] = [
            'id' => $bt->hashed_id,
            'payment_id' => $this->payment->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);

        $this->assertEquals($this->payment->refresh()->transaction_id, $bt->id);
        $this->assertEquals($bt->refresh()->payment_id, $this->payment->id);
        $this->assertEquals(BankTransaction::STATUS_CONVERTED, $bt->status_id);

    }


    public function testMatchBankTransactionsValidationShouldFail()
    {
        $data = [];
        
        $data['transactions'][] = [
            'bad_key' => 10,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(422);
    }


    public function testMatchBankTransactionValidationShouldPass()
    {

         if (config('ninja.testvars.travis') !== false) {
                $this->markTestSkipped('Skip test for Github Actions');
         }

        $data = [];

        $bi = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bi->save();

        $bt = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bt->bank_integration_id = $bi->id;
        $bt->description = 'Fuel';
        $bt->amount = 10;
        $bt->currency_code = $this->client->currency()->code;
        $bt->date = now()->format('Y-m-d');
        $bt->transaction_id = 1234567890;
        $bt->category_id = 10000003;
        $bt->base_type = 'DEBIT';
        $bt->save();

        $data = [];

        $data['transactions'][] = [
            'id' => $bt->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);
    }

}