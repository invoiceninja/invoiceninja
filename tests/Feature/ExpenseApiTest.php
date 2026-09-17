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
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\ExpenseController
 */
class ExpenseApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();
        Model::reguard();
    }

    public function testBulkUpdatesTaxes()
    {
        Expense::factory(5)->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $expenses = Expense::query()
                            ->where('company_id', $this->company->id)
                            ->where('client_id', $this->client->id)
                            ->where('vendor_id', $this->vendor->id);

        $this->assertCount(5, $expenses->get());

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'tax1',
            'new_value' => 'GST||10',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);


        $expenses->cursor()->each(function ($e) {
            $this->assertEquals('GST', $e->tax_name1);
            $this->assertEquals(10, $e->tax_rate1);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'custom_value1',
            'new_value' => 'CUSTOMCUSTOM123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertEquals('CUSTOMCUSTOM123', $e->custom_value1);
        });

        $data = [
                    'action' => 'bulk_update',
                    'ids' => $expenses->get()->pluck('hashed_id'),
                    'column' => 'should_be_invoiced',
                    'new_value' => false,
                ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertFalse((bool)$e->should_be_invoiced);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'should_be_invoiced',
            'new_value' => true,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertTrue((bool)$e->should_be_invoiced);
        });


        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'should_be_invoiced',
            'new_value' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertFalse((bool)$e->should_be_invoiced);
        });


        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'uses_inclusive_taxes',
            'new_value' => true,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertTrue((bool)$e->uses_inclusive_taxes);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'private_notes',
            'new_value' => 'TESTEST123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertEquals('TESTEST123', $e->private_notes);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'public_notes',
            'new_value' => 'TESTEST123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertEquals('TESTEST123', $e->private_notes);
        });



    }

    public function testBulkUpdateAcceptsCompanyProjectAndClient(): void
    {
        $other_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $other_expense = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $other_client->id,
        ]);

        $expenses = collect([$this->expense, $other_expense]);

        $expenses->each(function (Expense $expense): void {
            $expense->client_id = null;
            $expense->project_id = null;
            $expense->save();
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', [
            'action' => 'bulk_update',
            'ids' => $expenses->pluck('hashed_id')->all(),
            'column' => 'project_id',
            'new_value' => $this->project->hashed_id,
        ]);

        $response->assertStatus(200);

        $expenses->each(function (Expense $expense): void {
            $this->assertSame($this->project->id, $expense->fresh()->project_id);
            $this->assertSame($this->client->id, $expense->fresh()->client_id);
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', [
            'action' => 'bulk_update',
            'ids' => $expenses->pluck('hashed_id')->all(),
            'column' => 'client_id',
            'new_value' => $other_client->hashed_id,
        ]);

        $response->assertStatus(200);

        $expenses->each(function (Expense $expense) use ($other_client): void {
            $this->assertSame($other_client->id, $expense->fresh()->client_id);
            $this->assertNull($expense->fresh()->project_id);
        });
    }

    public function testBulkUpdateRejectsCrossCompanyProjectAndClient(): void
    {
        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $other_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $other_company->id,
        ]);

        $other_project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $other_company->id,
            'client_id' => $other_client->id,
        ]);

        foreach (['project_id' => $other_project, 'client_id' => $other_client] as $column => $entity) {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/expenses/bulk', [
                'action' => 'bulk_update',
                'ids' => [$this->expense->hashed_id],
                'column' => $column,
                'new_value' => $entity->hashed_id,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['new_value']);
        }

        $this->assertNull($this->expense->fresh()->project_id);
        $this->assertNull($this->expense->fresh()->client_id);
    }

    public function testBulkUpdateRejectsInvalidProjectAndClientValues(): void
    {
        foreach (['project_id', 'client_id'] as $column) {
            foreach (['invalid-entity-id', null, '', '   '] as $invalid_value) {
                $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/expenses/bulk', [
                    'action' => 'bulk_update',
                    'ids' => [$this->expense->hashed_id],
                    'column' => $column,
                    'new_value' => $invalid_value,
                ]);

                $response->assertStatus(422);
                $response->assertJsonValidationErrors(['new_value']);
            }
        }

        $this->assertNull($this->expense->fresh()->project_id);
        $this->assertNull($this->expense->fresh()->client_id);
    }

    public function testBulkUpdateAcceptsArchivedProjectAndClient(): void
    {
        $archived_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => false,
        ]);
        $archived_client->delete();

        $archived_project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $archived_client->id,
            'is_deleted' => false,
        ]);
        $archived_project->delete();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', [
            'action' => 'bulk_update',
            'ids' => [$this->expense->hashed_id],
            'column' => 'project_id',
            'new_value' => $archived_project->hashed_id,
        ]);

        $response->assertStatus(200);
        $this->assertSame($archived_project->id, $this->expense->fresh()->project_id);
        $this->assertSame($archived_client->id, $this->expense->fresh()->client_id);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', [
            'action' => 'bulk_update',
            'ids' => [$this->expense->hashed_id],
            'column' => 'client_id',
            'new_value' => $archived_client->hashed_id,
        ]);

        $response->assertStatus(200);
        $this->assertSame($archived_client->id, $this->expense->fresh()->client_id);
        $this->assertNull($this->expense->fresh()->project_id);
    }

    public function testBulkUpdateRejectsDeletedProjectAndClient(): void
    {
        $deleted_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => true,
        ]);
        $deleted_client->delete();

        $deleted_project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'is_deleted' => true,
        ]);
        $deleted_project->delete();

        foreach (['project_id' => $deleted_project, 'client_id' => $deleted_client] as $column => $entity) {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/expenses/bulk', [
                'action' => 'bulk_update',
                'ids' => [$this->expense->hashed_id],
                'column' => $column,
                'new_value' => $entity->hashed_id,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['new_value']);
        }

        $this->assertNull($this->expense->fresh()->project_id);
        $this->assertNull($this->expense->fresh()->client_id);
    }

    public function testVendorPayment()
    {
        $data = [
            'amount' => 100,
            'payment_date' => now()->format('Y-m-d'),
            'vendor_id' => $this->vendor->hashed_id,
            'date' => '2021-10-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses', $data);


        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($this->vendor->hashed_id, $arr['data']['vendor_id']);
        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['payment_date']);

        $data = [
            'amount' => 100,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/expenses/'.$arr['data']['id'], $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['payment_date']);

    }


    public function testExpensePutWithVendorStatus()
    {


        $data =
        [
            'vendor_id' => $this->vendor->hashed_id,
            'amount' => 10,
            'date' => '2021-10-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses', $data);

        $arr = $response->json();
        $response->assertStatus(200);


        $this->assertEquals($this->vendor->hashed_id, $arr['data']['vendor_id']);

        $data = [
            'payment_date' => now()->format('Y-m-d')
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->putJson('/api/v1/expenses/'.$arr['data']['id'], $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($this->vendor->hashed_id, $arr['data']['vendor_id']);

    }

    public function testTransactionIdClearedOnDelete()
    {
        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id
        ]);

        $bt = BankTransaction::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'bank_integration_id' => $bi->id,
        ]);

        $e = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'transaction_id' => $bt->id,
        ]);

        $this->assertNotNull($e->transaction_id);

        $expense_repo = app('App\Repositories\ExpenseRepository');
        $e = $expense_repo->delete($e);

        $this->assertNull($e->transaction_id);
    }

    public function testExpenseGetClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses?client_status=paid');

        $response->assertStatus(200);
    }

    public function testExpensePost()
    {
        $data = [
            'public_notes' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testExpensePostWithFilePayloadDoesNotCollideWithGlobalRules()
    {
        $data = [
            'public_notes' => $this->faker->firstName(),
            'file' => [UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.number'));
    }

    public function testDuplicateNumberCatch()
    {
        $data = [
            'public_notes' => $this->faker->firstName(),
            'number' => 'iamaduplicate',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $response->assertStatus(302);
    }

    public function testExpensePut()
    {
        $data = [
            'public_notes' => $this->faker->firstName(),
            'number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/', $data);

        $response->assertStatus(302);
    }

    public function testExpenseGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id));

        $response->assertStatus(200);
    }

    public function testExpenseGetSort()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses?sort=public_notes|desc');

        $response->assertStatus(200);
    }

    public function testExpenseNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testExpenseArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testExpenseRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testExpenseDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }

    public function testExpenseBulkCategorize()
    {

        $eXX = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);


        $e = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $ec = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Category',
        ]);

        $data = [
            'category_id' => $ec->hashed_id,
            'action' => 'bulk_categorize',
            'ids' => [$this->encodePrimaryKey($e->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk', $data);

        $arr = $response->json();

        $this->assertEquals($ec->hashed_id, $arr['data'][0]['category_id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/expenses");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertGreaterThan(1, count($arr['data']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/expenses?categories={$ec->hashed_id}");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(1, $arr['data']);

    }

    public function testAddingExpense()
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expense_categories', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $category_id = $arr['data']['id'];

        $data =
        [
            'vendor_id' => $this->vendor->hashed_id,
            'category_id' => $category_id,
            'amount' => 10,
            'date' => '2021-10-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }

    public function testPaymentTypeFilter(): void
    {
        Expense::query()->where('company_id', $this->company->id)->forceDelete();

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100,
            'payment_type_id' => 1,
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200,
            'payment_type_id' => 2,
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 50,
            'payment_type_id' => null,
        ]);

        // Filter by payment_type_id = 1
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses?payment_type=1');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertEquals(100, $arr['data'][0]['amount']);

        // Filter by payment_type_id = 2
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses?payment_type=2');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertEquals(200, $arr['data'][0]['amount']);

        // Filter by multiple payment types
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses?payment_type=1,2');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(2, $arr['data']);

        // No filter returns all
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(3, $arr['data']);
    }

    public function testHasInvoicesFilter()
    {
        $invoice = \App\Models\Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => \App\Models\Invoice::STATUS_SENT,
            'is_deleted' => 0,
        ]);

        $withInvoice = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
        ]);

        $withoutInvoice = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => null,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/expenses?has_invoices=client,' . $this->client->hashed_id . '&per_page=200')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($withInvoice->hashed_id, $ids);
        $this->assertNotContains($withoutInvoice->hashed_id, $ids);
    }
}
