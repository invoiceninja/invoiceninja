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

use Tests\TestCase;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\Factory\ClientFactory;
use App\Factory\CreditFactory;
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Utils\Traits\MakesHash;
use App\Helpers\Invoice\InvoiceSum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *  App\Utils\Traits\Payment\Refundable
 */
class RefundTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        // $this->withoutExceptionHandling();
    }

    public function testRefundAndAppliedAmounts()
    {

        $data = [
            'amount' => 500,
            'client_id' => $this->client->hashed_id,
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $item = new InvoiceItem();
        $item->cost = 300;
        $item->quantity = 1;

        $i = Invoice::factory()
        ->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'line_items' => [$item],
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
        ]);

        $i->calc()->getInvoice();
        $i->service()->markSent()->save();

        $this->assertEquals(300, $i->balance);

        $data = [
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                'invoice_id' => $i->hashed_id,
                'amount' => 300
                ],
            ]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$payment_id, $data);

        $response->assertStatus(200);

        $i = $i->fresh();

        $this->assertEquals(0, $i->balance);

        $payment = Payment::find($this->decodePrimaryKey($payment_id));

        $this->assertNotNull($payment);
        $this->assertEquals(500, $payment->amount);
        $this->assertEquals(300, $payment->applied);
        $this->assertEquals(0, $payment->refunded);

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => $i->amount,
                ],
            ],
            'date' => '2020/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $data);

        $response->assertStatus(200);

        $payment = $payment->fresh();
        $i = $i->fresh();

        $this->assertEquals(300, $payment->refunded);
        $this->assertEquals(300, $i->balance);
        $this->assertEquals(2, $i->status_id);


        $data = [
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                'invoice_id' => $i->hashed_id,
                'amount' => 200
                ],
            ]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$payment_id, $data);

        $response->assertStatus(200);

        $payment = $payment->fresh();
        $i = $i->fresh();

        $this->assertEquals(300, $payment->refunded);
        $this->assertEquals(100, $i->balance);
        $this->assertEquals(3, $i->status_id);
        $this->assertEquals(500, $payment->applied);

    }

    /**
     * Test that a simple payment of $50
     * is able to be refunded.
     */
    public function testBasicRefundValidation()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);
        $invoice_calc->build();

        $this->invoice = $invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => 50,
            'client_id' => $client->hashed_id,
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $this->assertEquals(50, $arr['data']['amount']);

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 50,
            'date' => '2020/12/12',
        ];

        $response = false;


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $data);


        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(50, $arr['data']['refunded']);
        $this->assertEquals(Payment::STATUS_REFUNDED, $arr['data']['status_id']);

    }

    /**
     * Test that a payment with Invoices
     * requires a refund with invoices specified.
     *
     * Should produce a validation error if
     * no invoices are specified in the refund
     */
    public function testRefundValidationNoInvoicesProvided()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);
        $invoice_calc->build();

        $this->invoice = $invoice_calc->getInvoice();
        $this->invoice->save();

        $this->invoice->setRelation('client', $this->client);
        $this->invoice->setRelation('company', $this->company);

        $this->invoice->service()->createInvitations()->markSent()->save();

        $this->assertNotNull($this->invoice->invitations);

        $this->assertNotNull($this->invoice->invitations->first()->contact);

        $data = [
            'amount' => 50,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $this->assertEquals(50, $arr['data']['amount']);

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 50,
            'date' => '2020/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $data);
        $response->assertStatus(422);

    }

    /**
     * Test that a refund with invoices provided
     * passes.
     */
    public function testRefundValidationWithValidInvoiceProvided()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);
        $invoice_calc->build();

        $this->invoice = $invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => 50,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $this->assertEquals(50, $arr['data']['amount']);

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());


        $i = $this->invoice->fresh();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(round($this->invoice->amount, 2), round($i->paid_to_date, 2));

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 50,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/refund', $data);

        $response->assertStatus(200);

        $i = $this->invoice->fresh();

        $this->assertEquals($i->amount, $i->balance);
        $this->assertEquals(0, round($i->paid_to_date, 2));

    }

    /**
     * Test Validation with incorrect invoice refund amounts.
     */
    public function testRefundValidationWithInValidInvoiceRefundedAmount()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);
        $invoice_calc->build();

        $this->invoice = $invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => 50,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $this->assertEquals(50, $arr['data']['amount']);

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 50,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 100,
                ],
            ],
            'date' => '2020/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/refund', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            \Log::error($message);
        }

        if ($response) {
            $response->assertStatus(302);
        }
    }

    /**
     * Tests refund when providing an invoice
     * not related to the payment.
     */
    public function testRefundValidationWithInValidInvoiceProvided()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);
        $invoice_calc->build();

        $this->invoice = $invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => 50,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $this->assertEquals(50, $arr['data']['amount']);

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);
        $invoice_calc->build();

        $this->invoice = $invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 50,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/refund', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            \Log::error($message);
        }

        if ($response) {
            $response->assertStatus(302);
        }
    }

    /**
     * Test refunds where payments include credits.
     *
     * $10 invoice
     * $10 credit
     * $50 credit card payment
     *
     *
     * result should be
     *
     * payment.applied = 10
     * credit.balance = 0
     */
    public function testRefundWhereCreditsArePresent()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;
        $this->invoice->client_id = $client->id;

        $this->invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->credit = CreditFactory::create($this->company->id, $this->user->id);
        $this->credit->client_id = $client->id;
        $this->credit->line_items = $this->buildLineItems();
        $this->credit->uses_inclusive_taxes = false;
        $this->credit->date = now()->format('Y-m-d');
        $this->credit->due_date = now()->addMonth()->format('Y-m-d');
        $this->credit->calc()->getCredit()->service()->markSent()->save();

        $this->assertEquals(10, $this->credit->amount);
        $this->assertEquals(10, $this->credit->balance);

        $data = [
            'amount' => 50,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $this->credit->hashed_id,
                    'amount' => $this->credit->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            \Log::error('this should not hit');
            \Log::error($message);
        }

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $this->assertEquals(50, $arr['data']['amount']);

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 50,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/refund', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            \Log::error('refund message error');
            \Log::error($message);
        }

        $response->assertStatus(200);
        $arr = $response->json();

        $payment = Payment::find($this->decodePrimaryKey($arr['data']['id']));
    }

    /*Additional scenarios*/

    public function testRefundsWhenCreditsArePresent()
    {
        $cl = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);


        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $cl->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 1000,
            'balance' => 1000,
        ]);

        $item = new InvoiceItem();
        $item->cost = 1000;
        $item->quantity = 1;

        $i->line_items = [$item];

        $i->service()->markSent()->save();

        $this->assertEquals(1000, $i->balance);

        $c = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $cl->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 100,
            'balance' => 100,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $item = new InvoiceItem();
        $item->cost = 100;
        $item->quantity = 1;

        $c->line_items = [$item];

        $c->service()->markSent()->save();

        $this->assertEquals(100, $c->balance);
        $this->assertNotNull($c);
        $this->assertEquals(2, $c->status_id);

        $this->assertEquals($cl->id, $c->client_id);

        $this->assertEquals($cl->id, $i->client_id);

        $data = [
            'amount' => 900,
            'client_id' => $cl->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => 1000,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $c->hashed_id,
                    'amount' => 100,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(0, $c->fresh()->balance);
        $this->assertEquals(0, $i->fresh()->balance);

        $payment_id = $arr['data']['id'];

        $refund = [
            'id' => $payment_id,
            'client_id' => $cl->hashed_id,
            'amount' => 10,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => 10,
                ],
            ]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $refund);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['refunded']);

        $this->assertEquals(10, $c->fresh()->balance);
        $this->assertEquals(10, $i->fresh()->balance);

    }

    public function testRefundsWithSplitCreditAndPaymentRefund()
    {
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 1000,
            'balance' => 1000,
        ]);

        $c = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 100,
            'balance' => 100,
        ]);

        $data = [
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => 1000,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $c->hashed_id,
                    'amount' => 100,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(0, $c->fresh()->balance);
        $this->assertEquals(0, $i->fresh()->balance);

        $payment_id = $arr['data']['id'];
        $payment = Payment::find($this->decodePrimaryKey($payment_id));

        $this->assertEquals(900, $payment->amount);
        $this->assertEquals(900, $payment->applied);
        $this->assertEquals(0, $payment->refunded);

        $refund = [
            'id' => $payment_id,
            'client_id' => $this->client->hashed_id,
            'amount' => 200,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => 200,
                ],
            ]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $refund);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(100, $arr['data']['refunded']);

        $this->assertEquals(100, $c->fresh()->balance);
        $this->assertEquals(200, $i->fresh()->balance);

        $this->assertEquals(900, $payment->fresh()->amount);
        $this->assertEquals(900, $payment->fresh()->applied);
        $this->assertEquals(100, $payment->fresh()->refunded);

    }

}
