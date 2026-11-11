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

use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyLedger;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\PaymentController
 */
class PaymentV2Test extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testUsingDraftCreditsForPayments()
    {

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'uses_inclusive_taxes' => false,
            'amount' => 20,
            'balance' => 20,
            'discount' => 0,
            'number' => uniqid("st", true),
            'line_items' => []
        ]);

        $item = InvoiceItemFactory::generateCredit();
        $item['cost'] = 20;
        $item['quantity'] = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Credit::STATUS_DRAFT,
            'uses_inclusive_taxes' => false,
            'amount' => 20,
            'balance' => 0,
            'discount' => 0,
            'number' => uniqid("st", true),
            'line_items' => [
                $item
            ]
        ]);

        $data = [
                    'client_id' => $this->client->hashed_id,
                    'invoices' => [
                        [
                            'invoice_id' => $invoice->hashed_id,
                            'amount' => 20,
                        ],
                    ],
                    'credits' => [
                        [
                            'credit_id' => $credit->hashed_id,
                            'amount' => 20,
                        ],
                    ],
                    'date' => '2020/12/12',

                ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];
        $this->assertEquals(Credit::STATUS_APPLIED, $credit->fresh()->status_id);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->fresh()->status_id);

        $this->assertEquals(0, $credit->fresh()->balance);
        $this->assertEquals(0, $invoice->fresh()->balance);

    }

    public function testStorePaymentWithCreditsThenDeletingInvoices()
    {
        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'balance' => 20, 'paid_to_date' => 0]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'uses_inclusive_taxes' => false,
            'amount' => 20,
            'balance' => 20,
            'discount' => 0,
            'number' => uniqid("st", true),
            'line_items' => []
        ]);

        $this->assertEquals(20, $client->balance);
        $this->assertEquals(0, $client->paid_to_date);
        $this->assertEquals(20, $invoice->amount);
        $this->assertEquals(20, $invoice->balance);

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'uses_inclusive_taxes' => false,
            'amount' => 20,
            'balance' => 20,
            'discount' => 0,
            'number' => uniqid("st", true),
            'line_items' => []
        ]);

        $this->assertEquals(20, $credit->amount);
        $this->assertEquals(20, $credit->balance);

        $data = [
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 20,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $credit->hashed_id,
                    'amount' => 20,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);


        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $payment = Payment::find($this->decodePrimaryKey($payment_id));

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());
        $this->assertEquals(0, $payment->amount);
        $this->assertEquals(0, $client->fresh()->balance);
        $this->assertEquals(20, $client->fresh()->paid_to_date);

        $data = [
            'action' => 'delete',
            'ids' => [
                $invoice->hashed_id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/bulk', $data);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();
        $payment = $payment->fresh();

        $this->assertEquals(true, $invoice->is_deleted);
        $this->assertEquals(0, $payment->amount);
        $this->assertEquals(0, $client->fresh()->balance);
        $this->assertEquals(0, $client->fresh()->paid_to_date);

        $data = [
            'action' => 'restore',
            'ids' => [
                $invoice->hashed_id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/bulk', $data);

        $invoice = $invoice->fresh();
        $this->assertEquals(false, $invoice->is_deleted);

        $payment = $payment->fresh();

        $this->assertEquals(0, $payment->amount);
        $this->assertEquals(20, $client->fresh()->paid_to_date);

    }

    public function testStorePaymentWithCreditsThenDeletingInvoicesAndThenPayments(): void
    {
        ['client' => $client, 'invoice' => $invoice, 'credit' => $credit, 'payment' => $payment] = $this->createCreditBackedPayment();

        $applied_state = $this->financialState($client, $invoice, $credit, $payment);

        $this->deleteInvoice($invoice);

        $invoice = Invoice::withTrashed()->findOrFail($invoice->id);
        $payment = Payment::withTrashed()->findOrFail($payment->id);

        $this->assertTrue($invoice->is_deleted);
        $this->assertTrue($invoice->trashed());
        $this->assertFalse($payment->is_deleted);
        $this->assertFalse($payment->trashed());

        $deleted_invoice_state = $this->financialState($client, $invoice, $credit, $payment);
        $ledger_state = $this->ledgerState($client);

        $this->withExceptionHandling();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/payments/bulk', [
                'action' => 'delete',
                'ids' => [$payment->hashed_id],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids')
            ->assertJsonPath('errors.ids.0', ctrans('texts.deleted_invoices_exist'));

        $this->assertSame(
            $deleted_invoice_state,
            $this->financialState($client, $invoice, $credit, $payment),
            'A rejected payment deletion must not mutate financial state.'
        );
        $this->assertSame(
            $ledger_state,
            $this->ledgerState($client),
            'A rejected payment deletion must not create or mutate ledger entries.'
        );

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'restore',
                'ids' => [$invoice->hashed_id],
            ]);

        $response->assertOk();

        $invoice = Invoice::withTrashed()->findOrFail($invoice->id);
        $payment = Payment::withTrashed()->findOrFail($payment->id);

        $this->assertFalse($invoice->is_deleted);
        $this->assertFalse($invoice->trashed());
        $this->assertSame(
            $applied_state,
            $this->financialState($client, $invoice, $credit, $payment),
            'Restoring the invoice must restore the complete applied-payment state.'
        );

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/payments/bulk', [
                'action' => 'delete',
                'ids' => [$payment->hashed_id],
            ]);

        $response->assertOk();

        $payment = Payment::withTrashed()->findOrFail($payment->id);
        $invoice = Invoice::withTrashed()->findOrFail($invoice->id);
        $client = $client->fresh();
        $credit = Credit::withTrashed()->findOrFail($credit->id);

        $this->assertTrue($payment->is_deleted);
        $this->assertTrue($payment->trashed());
        $this->assertEquals(100, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertEquals(100, $client->balance);
        $this->assertEquals(0, $client->paid_to_date);
        $this->assertEquals(20, $credit->balance);
        $this->assertEquals(Credit::STATUS_SENT, $credit->status_id);
        $this->assertSame(0, Paymentable::withTrashed()->where('payment_id', $payment->id)->count());
        $this->assertSame(
            1,
            CompanyLedger::query()
                ->where('client_id', $client->id)
                ->where('notes', "Adjusting invoice {$invoice->number} due to deletion of Payment {$payment->number}")
                ->count(),
            'Deleting the payment after restoring the invoice must write one invoice adjustment.'
        );
    }

    public function testDirectPaymentDeletionIsRejectedWhenLinkedInvoiceIsDeleted(): void
    {
        ['client' => $client, 'invoice' => $invoice, 'credit' => $credit, 'payment' => $payment] = $this->createCreditBackedPayment();

        $this->deleteInvoice($invoice);

        $invoice = Invoice::withTrashed()->findOrFail($invoice->id);
        $payment = Payment::withTrashed()->findOrFail($payment->id);
        $financial_state = $this->financialState($client, $invoice, $credit, $payment);
        $ledger_state = $this->ledgerState($client);

        $this->withExceptionHandling();

        $response = $this->withHeaders($this->apiHeaders())
            ->deleteJson("/api/v1/payments/{$payment->hashed_id}");

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id')
            ->assertJsonPath('errors.id.0', ctrans('texts.deleted_invoices_exist'));

        $this->assertSame($financial_state, $this->financialState($client, $invoice, $credit, $payment));
        $this->assertSame($ledger_state, $this->ledgerState($client));
    }

    public function testBulkPaymentDeletionRejectsEntireSelectionWhenOnePaymentHasDeletedInvoice(): void
    {
        ['client' => $client, 'invoice' => $invoice, 'credit' => $credit, 'payment' => $blocked_payment] = $this->createCreditBackedPayment();

        $this->deleteInvoice($invoice);

        $invoice = Invoice::withTrashed()->findOrFail($invoice->id);
        $blocked_payment = Payment::withTrashed()->findOrFail($blocked_payment->id);
        $deletable_payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'amount' => 25,
            'applied' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
        ])->fresh();

        $blocked_state = $this->financialState($client, $invoice, $credit, $blocked_payment);
        $deletable_state = $this->rawState($deletable_payment, [
            'amount',
            'applied',
            'status_id',
            'is_deleted',
            'deleted_at',
        ]);
        $ledger_state = $this->ledgerState($client);

        $this->withExceptionHandling();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/payments/bulk', [
                'action' => 'delete',
                'ids' => [$deletable_payment->hashed_id, $blocked_payment->hashed_id],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids')
            ->assertJsonPath('errors.ids.0', ctrans('texts.deleted_invoices_exist'));

        $this->assertSame($blocked_state, $this->financialState($client, $invoice, $credit, $blocked_payment));
        $this->assertSame(
            $deletable_state,
            $this->rawState(Payment::withTrashed()->findOrFail($deletable_payment->id), [
                'amount',
                'applied',
                'status_id',
                'is_deleted',
                'deleted_at',
            ]),
            'A mixed bulk request must not partially delete eligible payments.'
        );
        $this->assertSame($ledger_state, $this->ledgerState($client));
    }

    /**
     * @return array{client: Client, invoice: Invoice, credit: Credit, payment: Payment}
     */
    private function createCreditBackedPayment(): array
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'balance' => 100,
            'paid_to_date' => 0,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'uses_inclusive_taxes' => false,
            'amount' => 100,
            'balance' => 100,
            'discount' => 0,
            'number' => uniqid('st', true),
            'line_items' => [$item],
        ]);

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'uses_inclusive_taxes' => false,
            'amount' => 20,
            'balance' => 20,
            'discount' => 0,
            'number' => uniqid('st', true),
            'line_items' => [],
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/payments?include=invoices', [
                'client_id' => $client->hashed_id,
                'invoices' => [
                    [
                        'invoice_id' => $invoice->hashed_id,
                        'amount' => 100,
                    ],
                ],
                'credits' => [
                    [
                        'credit_id' => $credit->hashed_id,
                        'amount' => 20,
                    ],
                ],
                'date' => '2020/12/12',
            ]);

        $response->assertOk();

        $payment = Payment::findOrFail($this->decodePrimaryKey($response->json('data.id')));

        $this->assertSame(1, $payment->invoices()->count());
        $this->assertEquals(80, $payment->amount);
        $this->assertEquals(0, $client->fresh()->balance);
        $this->assertEquals(100, $client->fresh()->paid_to_date);
        $this->assertEquals(0, $credit->fresh()->balance);

        return [
            'client' => $client,
            'invoice' => $invoice,
            'credit' => $credit,
            'payment' => $payment,
        ];
    }

    private function deleteInvoice(Invoice $invoice): void
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'delete',
                'ids' => [$invoice->hashed_id],
            ]);

        $response->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function financialState(Client $client, Invoice $invoice, Credit $credit, Payment $payment): array
    {
        return [
            'client' => $this->rawState($client->fresh(), ['balance', 'paid_to_date', 'credit_balance']),
            'invoice' => $this->rawState(Invoice::withTrashed()->findOrFail($invoice->id), [
                'number',
                'balance',
                'paid_to_date',
                'status_id',
                'is_deleted',
                'deleted_at',
            ]),
            'credit' => $this->rawState(Credit::withTrashed()->findOrFail($credit->id), [
                'balance',
                'paid_to_date',
                'status_id',
                'is_deleted',
                'deleted_at',
            ]),
            'payment' => $this->rawState(Payment::withTrashed()->findOrFail($payment->id), [
                'amount',
                'applied',
                'status_id',
                'is_deleted',
                'deleted_at',
            ]),
            'paymentables' => Paymentable::withTrashed()
                ->where('payment_id', $payment->id)
                ->orderBy('id')
                ->get()
                ->map(fn(Paymentable $paymentable): array => $this->rawState($paymentable, [
                    'id',
                    'paymentable_id',
                    'paymentable_type',
                    'amount',
                    'refunded',
                    'deleted_at',
                ]))
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ledgerState(Client $client): array
    {
        return CompanyLedger::query()
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->get()
            ->map(fn(CompanyLedger $ledger): array => $this->rawState($ledger, [
                'id',
                'adjustment',
                'balance',
                'activity_id',
                'notes',
            ]))
            ->all();
    }

    /**
     * @param array<int, string> $attributes
     * @return array<string, mixed>
     */
    private function rawState(Model $model, array $attributes): array
    {
        return collect($attributes)
            ->mapWithKeys(fn(string $attribute): array => [$attribute => $model->getRawOriginal($attribute)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

    }

}
