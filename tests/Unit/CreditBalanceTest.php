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

use App\Models\Credit;
use App\Utils\Traits\AppSetup;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * @test
 */
class CreditBalanceTest extends TestCase
{
    use MockUnitData;
    use AppSetup;

    protected function setUp(): void
    {
        parent::setUp();

        Credit::all()->each(function ($credit) {
            $credit->forceDelete();
        });

        $this->makeTestData();
    }

    public function testCreditBalance()
    {
        $credit = Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 10,
            'number' => 'testing-number-01',
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->assertEquals($this->client->service()->getCreditBalance(), 10);
    }

    public function testExpiredCreditBalance()
    {
        $credit = Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 10,
            'due_date' => now()->addDays(5),
            'number' => 'testing-number-02',
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->assertEquals($this->client->service()->getCreditBalance(), 0);
    }

    public function testCreditDeleteCheckClientBalance()
    {
        $credit = Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 10,
            'number' => 'testing-number-01',
            'status_id' => Credit::STATUS_SENT,
        ]);

        $credit->client->credit_balance = 10;
        $credit->push();


        //delete invoice
        $data = [
            'ids' => [$credit->hashed_id],
        ];

        //restore invoice
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk?action=delete', $data)->assertStatus(200);

        $client = $credit->client->fresh();

        $this->assertEquals(0, $client->credit_balance);

        //restore invoice
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk?action=restore', $data)->assertStatus(200);

        $client = $credit->client->fresh();

        $this->assertEquals(10, $client->credit_balance);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk?action=archive', $data)->assertStatus(200);

        $client = $credit->client->fresh();

        $this->assertEquals(10, $client->credit_balance);
    }
}
