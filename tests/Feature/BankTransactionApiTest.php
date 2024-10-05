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

use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;


class BankTransactionApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testBankTransactionCreate()
    {

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/bank_transactions/create');

        $response->assertStatus(200);
    }


    public function testBankTransactionGetClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/bank_transactions?client_status=unmatched'.$this->encodePrimaryKey($this->bank_transaction->id));

        $response->assertStatus(200);
    }

    public function testBankTransactionGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/bank_transactions/'.$this->encodePrimaryKey($this->bank_transaction->id));

        $response->assertStatus(200);
    }

    public function testBankTransactionArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->bank_transaction->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transactions/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testBankTransactionRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->bank_transaction->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transactions/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testBankTransactionDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->bank_transaction->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transactions/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }

    public function testBankTransactionUnlink()
    {
        BankTransaction::truncate();

        $bi = BankIntegration::factory()->create([
            'account_id' => $this->account->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $e = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $bank_transaction = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'payment_id' => $this->payment->id,
            'expense_id' => "{$this->expense->hashed_id},{$e->hashed_id}",
            'invoice_ids' => $this->invoice->hashed_id,
        ]);

        $e->transaction_id = $bank_transaction->id;
        $e->save();

        $this->expense->transaction_id = $bank_transaction->id;
        $this->expense->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($bank_transaction->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transactions/bulk?action=unlink', $data);

        $arr = $response->json();

        $this->assertEquals(1, $arr['data'][0]['status_id']);
        $this->assertEquals("", $arr['data'][0]['payment_id']);
        $this->assertEquals("", $arr['data'][0]['invoice_ids']);
        $this->assertEquals("", $arr['data'][0]['expense_id']);

        $this->assertNull($e->fresh()->transaction_id);
        $this->assertNull($this->expense->fresh()->transaction_id);
    }

}
