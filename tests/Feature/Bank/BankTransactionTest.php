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
        $data = [];

        $data['transactions'][] = [
            'id' => $this->bank_transaction->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);
    }

}