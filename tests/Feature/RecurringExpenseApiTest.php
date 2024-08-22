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

use App\Jobs\Cron\RecurringExpensesCron;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\RecurringExpenseController
 */
class RecurringExpenseApiTest extends TestCase
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

    public function testRecurringExpenseGenerationWithCurrencyConversion()
    {
        $r = \App\Models\RecurringExpense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100,
            'number' => Str::random(10),
            'frequency_id' => 5,
            'remaining_cycles' => -1,
            'status_id' => \App\Models\RecurringInvoice::STATUS_ACTIVE,
            'date' => now()->format('Y-m-d'),
            'currency_id' => 1,
            'next_send_date' => now(),
            'next_send_date_client' => now(),
            'invoice_currency_id' => 2,
            'foreign_amount' => 50,
        ]);

        (new RecurringExpensesCron())->handle();

        $expense = \App\Models\Expense::where('recurring_expense_id', $r->id)->orderBy('id', 'desc')->first();

        $this->assertEquals($r->amount, $expense->amount);
        $this->assertEquals($r->currency_id, $expense->currency_id);
        $this->assertEquals($r->invoice_currency_id, $expense->invoice_currency_id);
        $this->assertEquals($r->foreign_amount, $expense->foreign_amount);

    }

    public function testRecurringExpenseGenerationNullForeignCurrency()
    {
        $r = \App\Models\RecurringExpense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100,
            'number' => Str::random(10),
            'frequency_id' => 5,
            'remaining_cycles' => -1,
            'status_id' => \App\Models\RecurringInvoice::STATUS_ACTIVE,
            'date' => now()->format('Y-m-d'),
            'currency_id' => 1,
            'next_send_date' => now(),
            'next_send_date_client' => now(),
            'invoice_currency_id' => null
        ]);

        (new RecurringExpensesCron())->handle();

        $expense = \App\Models\Expense::where('recurring_expense_id', $r->id)->orderBy('id', 'desc')->first();

        $this->assertEquals($r->amount, $expense->amount);
        $this->assertEquals($r->currency_id, $expense->currency_id);
        $this->assertEquals($r->invoice_currency_id, $expense->invoice_currency_id);

    }

    public function testRecurringExpenseGeneration()
    {
        $r = \App\Models\RecurringExpense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100,
            'number' => Str::random(10),
            'frequency_id' => 5,
            'remaining_cycles' => -1,
            'status_id' => \App\Models\RecurringInvoice::STATUS_ACTIVE,
            'date' => now()->format('Y-m-d'),
            'currency_id' => 1,
            'next_send_date' => now(),
            'next_send_date_client' => now(),
        ]);

        (new RecurringExpensesCron())->handle();

        $expense = \App\Models\Expense::where('recurring_expense_id', $r->id)->orderBy('id', 'desc')->first();

        $this->assertEquals($r->amount, $expense->amount);
        $this->assertEquals($r->currency_id, $expense->currency_id);

    }

    public function testRecurringExpenseValidation()
    {
        $data = [
            'amount' => 10,
            'client_id' => $this->client->hashed_id,
            'number' => '123321',
            'frequency_id' => 5,
            'remaining_cycles' => 5,
            'currency_id' => 34545435425
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_expenses?start=true', $data);

        $response->assertStatus(422);

    }

    public function testRecurringExpenseValidation2()
    {
        $data = [
            'amount' => 10,
            'client_id' => $this->client->hashed_id,
            'number' => '123321',
            'frequency_id' => 5,
            'remaining_cycles' => 5,
            'currency_id' => 1
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_expenses?start=true', $data);

        $response->assertStatus(200);

    }

    public function testRecurringExpenseValidation3()
    {
        $data = [
            'amount' => 10,
            'client_id' => $this->client->hashed_id,
            'number' => '123321',
            'frequency_id' => 5,
            'remaining_cycles' => 5,
            'currency_id' => null
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_expenses?start=true', $data);

        $data = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(1, $data['data']['currency_id']);

    }

    public function testRecurringExpenseValidation4()
    {
        $data = [
            'amount' => 10,
            'client_id' => $this->client->hashed_id,
            'number' => '123321',
            'frequency_id' => 5,
            'remaining_cycles' => 5,
            'currency_id' => ""
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_expenses?start=true', $data);

        $data = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(1, $data['data']['currency_id']);

    }

    public function testRecurringExpenseGetFiltered()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_expenses?filter=xx');

        $response->assertStatus(200);
    }

    public function testRecurringExpenseGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_expenses/');

        $response->assertStatus(200);
    }

    public function testRecurringExpenseGetSingleExpense()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_expenses/'.$this->recurring_expense->hashed_id);

        $response->assertStatus(200);
    }

    public function testRecurringExpensePost()
    {
        $data = [
            'amount' => 10,
            'client_id' => $this->client->hashed_id,
            'number' => '123321',
            'frequency_id' => 5,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_expenses/'.$arr['data']['id'], $data)->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/recurring_expenses', $data);
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }
    }

    public function testRecurringExpensePut()
    {
        $data = [
            'amount' => 20,
            'public_notes' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_expenses/'.$this->encodePrimaryKey($this->recurring_expense->id), $data);

        $response->assertStatus(200);
    }

    public function testRecurringExpenseNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_expenses/'.$this->encodePrimaryKey($this->recurring_expense->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testRecurringExpenseArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testRecurringExpenseRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testRecurringExpenseDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }

    public function testRecurringExpenseStart()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses/bulk?action=start', $data);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_ACTIVE, $arr['data'][0]['status_id']);
    }

    public function testRecurringExpensePaused()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses/bulk?action=start', $data);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses/bulk?action=stop', $data);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_PAUSED, $arr['data'][0]['status_id']);
    }

    public function testRecurringExpenseStartedWithTriggeredAction()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_expenses/'.$this->recurring_expense->hashed_id.'?start=true', []);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_ACTIVE, $arr['data']['status_id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_expenses/'.$this->recurring_expense->hashed_id.'?stop=true', []);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_PAUSED, $arr['data']['status_id']);
    }

    public function testRecurringExpensePostWithStartAction()
    {
        $data = [
            'amount' => 10,
            'client_id' => $this->client->hashed_id,
            'number' => '123321',
            'frequency_id' => 5,
            'remaining_cycles' => 5,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses?start=true', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_ACTIVE, $arr['data']['status_id']);
    }

    public function testRecurringExpensePostWithStopAction()
    {
        $data = [
            'amount' => 10,
            'client_id' => $this->client->hashed_id,
            'number' => '1233x21',
            'frequency_id' => 5,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_expenses?stop=true', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_PAUSED, $arr['data']['status_id']);
    }
}
