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

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use Tests\MockAccountData;
use App\Factory\InvoiceFactory;
use App\Jobs\Bank\MatchBankTransactions;
use App\Models\BankTransaction;
use App\Factory\InvoiceItemFactory;
use App\Factory\BankIntegrationFactory;
use App\Factory\BankTransactionFactory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BankTransactionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testBankIntegrationFilters()
    {
        BankTransaction::where('company_id', $this->company->id)
        ->cursor()->each(function ($bt) {
            $bt->forceDelete();
        });

        $bi = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bi->bank_account_name = "Bank1";
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


        $bi2 = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bi2->bank_account_name = "Bank2";
        $bi2->save();

        $bt = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bt->bank_integration_id = $bi2->id;
        $bt->status_id = BankTransaction::STATUS_UNMATCHED;
        $bt->description = 'Fuel';
        $bt->amount = 20;
        $bt->currency_code = $this->client->currency()->code;
        $bt->date = now()->format('Y-m-d');
        $bt->transaction_id = 1234567890;
        $bt->category_id = 10000003;
        $bt->base_type = 'DEBIT';
        $bt->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/bank_transactions');

        $response->assertStatus(200);

        $arr = $response->json();

        $transaction_count = count($arr['data']);

        $this->assertGreaterThan(1, $transaction_count);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/bank_transactions?bank_integration_ids='.$bi->hashed_id);

        $response->assertStatus(200);

        $arr = $response->json();

        $transaction_count = count($arr['data']);

        $this->assertCount(1, $arr['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/bank_transactions?bank_integration_ids='.$bi2->hashed_id);

        $response->assertStatus(200);

        $arr = $response->json();

        $transaction_count = count($arr['data']);

        $this->assertCount(1, $arr['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/bank_transactions?bank_integration_ids='.$bi2->hashed_id.",".$bi->hashed_id);

        $response->assertStatus(200);

        $arr = $response->json();

        $transaction_count = count($arr['data']);

        $this->assertCount(2, $arr['data']);

        $bi2->delete();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/bank_transactions?active_banks=true');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(1, $arr['data']);

    }

    public function testLinkMultipleExpensesWithDeleteToTransaction()
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

        $this->expense->vendor_id = $this->vendor->id;
        $this->expense->save();

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
        $this->assertEquals($this->expense->hashed_id, $bt->refresh()->expense_id);
        $this->assertEquals($bt->id, $this->expense->transaction_id);
        $this->assertEquals($this->vendor->id, $bt->vendor_id);
        $this->assertEquals(BankTransaction::STATUS_CONVERTED, $bt->status_id);


        $e = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $data = [];

        $data['transactions'][] = [
            'id' => $bt->hashed_id,
            'expense_id' => $e->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);

        $this->assertEquals("{$this->expense->hashed_id},{$e->hashed_id}", $bt->fresh()->expense_id);

        $e2 = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $data = [];

        $data['transactions'][] = [
            'id' => $bt->hashed_id,
            'expense_id' => $e2->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);

        $this->assertNotNull($e2->refresh()->transaction_id);

        $this->assertEquals("{$this->expense->hashed_id},{$e->hashed_id},{$e2->hashed_id}", $bt->fresh()->expense_id);

        $expense_repo = app('App\Repositories\ExpenseRepository');

        $expense_repo->delete($e2);

        $this->assertEquals("{$this->expense->hashed_id},{$e->hashed_id}", $bt->fresh()->expense_id);

    }



    public function testLinkMultipleExpensesToTransaction()
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

        $this->expense->vendor_id = $this->vendor->id;
        $this->expense->save();

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
        $this->assertEquals($this->expense->hashed_id, $bt->refresh()->expense_id);
        $this->assertEquals($this->vendor->id, $bt->vendor_id);
        $this->assertEquals(BankTransaction::STATUS_CONVERTED, $bt->status_id);


        $e = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $data = [];

        $data['transactions'][] = [
            'id' => $bt->hashed_id,
            'expense_id' => $e->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);

        $this->assertEquals("{$this->expense->hashed_id},{$e->hashed_id}", $bt->fresh()->expense_id);

    }


    public function testBankTransactionBulkActions()
    {
        $data = [
            'ids' => [$this->bank_integration->hashed_id],
            'action' => 'archive'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transactions/bulk', $data)
          ->assertStatus(200);

        $data = [
            'ids' => [$this->bank_integration->hashed_id],
            'action' => 'restore'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transactions/bulk', $data)
          ->assertStatus(200);

        $data = [
            'ids' => [$this->bank_integration->hashed_id],
            'action' => 'delete'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transactions/bulk', $data)
          ->assertStatus(200);
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

        $this->expense->vendor_id = $this->vendor->id;
        $this->expense->save();

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
        $this->assertEquals($this->expense->hashed_id, $bt->refresh()->expense_id);
        $this->assertEquals($this->vendor->id, $bt->vendor_id);
        $this->assertEquals(BankTransaction::STATUS_CONVERTED, $bt->status_id);
    }

    public function testLinkingManuallyPaidInvoices()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->status_id = Invoice::STATUS_SENT;
        $invoice->number = "InvoiceMatchingNumber123";
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 325;
        $item->type_id = 1;

        $line_items[] = $item;

        $invoice->line_items = $line_items;

        $invoice = $invoice->calc()->getInvoice();

        $invoice->service()->markPaid();

        $p = $invoice->payments->first();


        $bi = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bi->save();

        $bt = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bt->bank_integration_id = $bi->id;
        $bt->status_id = BankTransaction::STATUS_UNMATCHED;
        $bt->description = 'InvoiceMatchingNumber123';
        $bt->amount = 325;
        $bt->currency_code = $this->client->currency()->code;
        $bt->date = now()->format('Y-m-d');
        $bt->transaction_id = 1234567890;
        $bt->category_id = 10000003;
        $bt->base_type = 'CREDIT';
        $bt->save();

        $data = [];

        $data['transactions'][] = [
            'id' => $bt->hashed_id,
            'payment_id' => $p->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', $data);

        $response->assertStatus(200);

        $this->assertEquals($p->refresh()->transaction_id, $bt->id);
        $this->assertEquals($bt->refresh()->payment_id, $p->id);
        $this->assertEquals(BankTransaction::STATUS_CONVERTED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
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

    public function testMatchBankTransactionRejectsBlankShouldBeInvoiced(): void
    {
        foreach ([null, '', '   '] as $blank_value) {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/bank_transactions/match', [
                'transactions' => [
                    [
                        'id' => $this->bank_transaction->hashed_id,
                        'should_be_invoiced' => $blank_value,
                    ],
                ],
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['transactions.0.should_be_invoiced']);
        }
    }

    public function testMatchBankTransactionAcceptsBlankOptionalEntityIds(): void
    {
        foreach ([null, '', '   '] as $blank_value) {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/bank_transactions/match', [
                'transactions' => [
                    [
                        'id' => $this->bank_transaction->hashed_id,
                        'invoice_ids' => $blank_value,
                        'payment_id' => $blank_value,
                        'expense_id' => $blank_value,
                        'vendor_id' => $blank_value,
                        'ninja_category_id' => $blank_value,
                        'project_id' => $blank_value,
                        'client_id' => $blank_value,
                    ],
                ],
            ]);

            $response->assertStatus(200);
        }

        $this->assertFalse(Expense::query()->where('transaction_id', $this->bank_transaction->id)->exists());
    }

    public function testMatchBankTransactionRejectsInvalidProjectAndClientIds(): void
    {
        foreach (['project_id', 'client_id'] as $field) {
            foreach ([[], 'invalid-entity-id', false] as $invalid_value) {
                $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/bank_transactions/match', [
                    'transactions' => [
                        [
                            'id' => $this->bank_transaction->hashed_id,
                            'ninja_category_id' => $this->expense_category->hashed_id,
                            $field => $invalid_value,
                        ],
                    ],
                ]);

                $response->assertStatus(422);
                $response->assertJsonValidationErrors(["transactions.0.{$field}"]);
            }
        }

        $this->assertFalse(Expense::query()->where('transaction_id', $this->bank_transaction->id)->exists());
    }

    public function testMatchBankTransactionsUsesPaymentWhenInvoiceIdsAreEmpty(): void
    {
        $this->payment->transaction_id = null;
        $this->payment->save();

        $this->bank_transaction->status_id = BankTransaction::STATUS_UNMATCHED;
        $this->bank_transaction->payment_id = null;
        $this->bank_transaction->save();

        (new MatchBankTransactions($this->company->id, $this->company->db, [
            'transactions' => [
                [
                    'id' => $this->bank_transaction->id,
                    'invoice_ids' => '',
                    'payment_id' => $this->payment->id,
                ],
            ],
        ]))->handle();

        $this->assertSame(BankTransaction::STATUS_CONVERTED, $this->bank_transaction->fresh()->status_id);
        $this->assertSame($this->payment->id, $this->bank_transaction->fresh()->payment_id);
        $this->assertSame($this->bank_transaction->id, $this->payment->fresh()->transaction_id);
    }

    public function testMatchBankTransactionStopsProcessingAnAlreadyLinkedPayment(): void
    {
        $this->payment->transaction_id = $this->bank_transaction->id;
        $this->payment->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', [
            'transactions' => [
                [
                    'id' => $this->bank_transaction->hashed_id,
                    'payment_id' => $this->payment->hashed_id,
                    'ninja_category_id' => $this->expense_category->hashed_id,
                    'project_id' => $this->project->hashed_id,
                    'client_id' => $this->client->hashed_id,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertSame($this->bank_transaction->id, $this->payment->fresh()->transaction_id);
        $this->assertFalse(Expense::query()->where('transaction_id', $this->bank_transaction->id)->exists());
    }

    public function testMatchBankTransactionCreatesExpenseWithProjectAndInvoiceableOverride(): void
    {
        $this->company->mark_expenses_invoiceable = true;
        $this->company->save();

        $other_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $bank_integration = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bank_integration->save();

        $bank_transaction = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bank_transaction->bank_integration_id = $bank_integration->id;
        $bank_transaction->status_id = BankTransaction::STATUS_UNMATCHED;
        $bank_transaction->description = 'Project expense';
        $bank_transaction->amount = 100;
        $bank_transaction->currency_code = $this->client->currency()->code;
        $bank_transaction->date = now()->format('Y-m-d');
        $bank_transaction->transaction_id = 987654321;
        $bank_transaction->category_id = 10000003;
        $bank_transaction->base_type = 'DEBIT';
        $bank_transaction->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', [
            'transactions' => [
                [
                    'id' => $bank_transaction->hashed_id,
                    'ninja_category_id' => $this->expense_category->hashed_id,
                    'project_id' => $this->project->hashed_id,
                    'client_id' => $other_client->hashed_id,
                    'should_be_invoiced' => false,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $expense = Expense::query()->where('transaction_id', $bank_transaction->id)->firstOrFail();

        $this->assertSame($this->project->id, $expense->project_id);
        $this->assertSame($this->project->client_id, $expense->client_id);
        $this->assertFalse((bool) $expense->should_be_invoiced);
    }

    public function testMatchBankTransactionCreatesExpenseWithClientAndCompanyInvoiceableDefault(): void
    {
        $this->company->mark_expenses_invoiceable = true;
        $this->company->save();

        $bank_integration = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bank_integration->save();

        $bank_transaction = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bank_transaction->bank_integration_id = $bank_integration->id;
        $bank_transaction->status_id = BankTransaction::STATUS_UNMATCHED;
        $bank_transaction->description = 'Client expense';
        $bank_transaction->amount = 75;
        $bank_transaction->currency_code = $this->client->currency()->code;
        $bank_transaction->date = now()->format('Y-m-d');
        $bank_transaction->transaction_id = 987654322;
        $bank_transaction->category_id = 10000003;
        $bank_transaction->base_type = 'DEBIT';
        $bank_transaction->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transactions/match', [
            'transactions' => [
                [
                    'id' => $bank_transaction->hashed_id,
                    'ninja_category_id' => $this->expense_category->hashed_id,
                    'project_id' => null,
                    'client_id' => $this->client->hashed_id,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $expense = Expense::query()->where('transaction_id', $bank_transaction->id)->firstOrFail();

        $this->assertSame($this->client->id, $expense->client_id);
        $this->assertNull($expense->project_id);
        $this->assertTrue((bool) $expense->should_be_invoiced);
    }

    public function testMatchBankTransactionCoercesInvoiceableValuesAndHonorsFalseDefault(): void
    {
        $cases = [
            ['company_default' => false, 'expected' => false],
            ['company_default' => false, 'input' => true, 'expected' => true],
            ['company_default' => false, 'input' => 'true', 'expected' => true],
            ['company_default' => false, 'input' => '1', 'expected' => true],
            ['company_default' => false, 'input' => 1, 'expected' => true],
            ['company_default' => true, 'input' => 'false', 'expected' => false],
            ['company_default' => true, 'input' => '0', 'expected' => false],
            ['company_default' => true, 'input' => 0, 'expected' => false],
        ];

        $bank_integration = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bank_integration->save();

        foreach ($cases as $index => $case) {
            $this->company->mark_expenses_invoiceable = $case['company_default'];
            $this->company->save();

            $bank_transaction = BankTransactionFactory::create($this->company->id, $this->user->id);
            $bank_transaction->bank_integration_id = $bank_integration->id;
            $bank_transaction->status_id = BankTransaction::STATUS_UNMATCHED;
            $bank_transaction->description = "Invoiceable coercion {$index}";
            $bank_transaction->amount = 50 + $index;
            $bank_transaction->currency_code = $this->client->currency()->code;
            $bank_transaction->date = now()->format('Y-m-d');
            $bank_transaction->transaction_id = 987655000 + $index;
            $bank_transaction->category_id = 10000003;
            $bank_transaction->base_type = 'DEBIT';
            $bank_transaction->save();

            $transaction = [
                'id' => $bank_transaction->hashed_id,
                'ninja_category_id' => $this->expense_category->hashed_id,
                'client_id' => $this->client->hashed_id,
            ];

            if (array_key_exists('input', $case)) {
                $transaction['should_be_invoiced'] = $case['input'];
            }

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/bank_transactions/match', [
                'transactions' => [$transaction],
            ]);

            $response->assertStatus(200);

            $expense = Expense::query()->where('transaction_id', $bank_transaction->id)->firstOrFail();

            $this->assertSame($case['expected'], (bool) $expense->should_be_invoiced);
        }
    }

    public function testMatchBankTransactionRejectsCrossCompanyProjectAndClient(): void
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

        $bank_integration = BankIntegrationFactory::create($this->company->id, $this->user->id, $this->account->id);
        $bank_integration->save();

        $bank_transaction = BankTransactionFactory::create($this->company->id, $this->user->id);
        $bank_transaction->bank_integration_id = $bank_integration->id;
        $bank_transaction->status_id = BankTransaction::STATUS_UNMATCHED;
        $bank_transaction->description = 'Cross-company expense';
        $bank_transaction->amount = 50;
        $bank_transaction->currency_code = $this->client->currency()->code;
        $bank_transaction->date = now()->format('Y-m-d');
        $bank_transaction->transaction_id = 987654323;
        $bank_transaction->category_id = 10000003;
        $bank_transaction->base_type = 'DEBIT';
        $bank_transaction->save();

        foreach (['project_id' => $other_project, 'client_id' => $other_client] as $field => $entity) {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/bank_transactions/match', [
                'transactions' => [
                    [
                        'id' => $bank_transaction->hashed_id,
                        'ninja_category_id' => $this->expense_category->hashed_id,
                        $field => $entity->hashed_id,
                    ],
                ],
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(["transactions.0.{$field}"]);
        }

        $this->assertFalse(Expense::query()->where('transaction_id', $bank_transaction->id)->exists());
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
