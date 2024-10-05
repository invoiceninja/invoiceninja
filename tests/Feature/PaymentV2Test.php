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
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
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

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

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

    public function testStorePaymentWithCreditsThenDeletingInvoicesAndThenPayments()
    {
        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'balance' => 100, 'paid_to_date' => 0]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;

        $line_items[] = $item;


        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'uses_inclusive_taxes' => false,
            'amount' => 100,
            'balance' => 100,
            'discount' => 0,
            'number' => uniqid("st", true),
            'line_items' => $line_items
        ]);

        $this->assertEquals(100, $client->balance);
        $this->assertEquals(0, $client->paid_to_date);
        $this->assertEquals(100, $invoice->amount);
        $this->assertEquals(100, $invoice->balance);

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
        $credit = $credit->fresh();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());
        $this->assertEquals(80, $payment->amount);
        $this->assertEquals(0, $client->fresh()->balance);
        $this->assertEquals(100, $client->fresh()->paid_to_date);
        $this->assertEquals(0, $credit->balance);

        $invoice = $invoice->fresh();

        //delete the invoice

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

        $payment = $payment->fresh();
        $invoice = $invoice->fresh();

        $this->assertTrue($invoice->is_deleted);
        $this->assertFalse($payment->is_deleted);

        $data = [
            'action' => 'delete',
            'ids' => [
                $payment->hashed_id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk', $data);

        $payment = $payment->fresh();
        $this->assertTrue($payment->is_deleted);

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

        $response->assertStatus(200);
        $invoice = $invoice->fresh();

        $this->assertTrue($invoice->is_deleted);
        $this->assertTrue($invoice->trashed());

        $client = $client->fresh();
        $credit = $credit->fresh();

        $this->assertEquals(0, $client->balance);
        $this->assertEquals(0, $client->paid_to_date);
        // $this->assertEquals(20, $client->credit_balance);
        $this->assertEquals(20, $credit->balance);

    }

}
