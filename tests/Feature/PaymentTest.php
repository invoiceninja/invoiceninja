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

use App\DataMapper\ClientSettings;
use App\Factory\ClientFactory;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutEvents;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\PaymentController
 */
class PaymentTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    use WithoutEvents;

    protected function setUp() :void
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

    public function testPaymentList()
    {
        Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
            ]);
        });

        $client = Client::all()->first();

        Payment::factory()->create(
            ['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $client->id]
        );

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments');

        $response->assertStatus(200);
    }

    public function testPaymentRESTEndPoints()
    {
        Payment::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $Payment = Payment::all()->last();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments/'.$this->encodePrimaryKey($Payment->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/payments/'.$this->encodePrimaryKey($Payment->id), $Payment->toArray());

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments/'.$this->encodePrimaryKey($Payment->id).'/edit');

        $response->assertStatus(200);
    }

    public function testStorePaymentWithoutClientId()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();

        $data = [
            'amount' => $this->invoice->amount,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/11',

        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertTrue(array_key_exists('client_id', $message));
        }
    }

    public function testStorePaymentWithClientId()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices,paymentables', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $payment_id = $arr['data']['id'];

            $payment = Payment::find($this->decodePrimaryKey($payment_id))->first();
            $payment->load('invoices');

            $this->assertNotNull($payment);
            $this->assertNotNull($payment->invoices());
            $this->assertEquals(1, $payment->invoices()->count());
        }
    }

    public function testStorePaymentWithNoInvoiecs()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => '',
            'date' => '2020/12/12',

        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $response->assertStatus(200);
        }
    }

    public function testPartialPaymentAmount()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' =>$this->company->id,
            'is_primary' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->partial = 2.0;
        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $response->assertStatus(200);

            $arr = $response->json();

            $payment_id = $arr['data']['id'];

            $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

            $this->assertNotNull($payment);
            $this->assertNotNull($payment->invoices());
            $this->assertEquals(1, $payment->invoices()->count());

            $pivot_invoice = $payment->invoices()->first();
            $this->assertEquals($pivot_invoice->pivot->amount, 2);
            $this->assertEquals($pivot_invoice->partial, 0);
            $this->assertEquals($pivot_invoice->amount, 10.0000);
            $this->assertEquals($pivot_invoice->balance, 8.0000);
        }
    }

    public function testPaymentGreaterThanPartial()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->setRelation('company', $this->company);
        $client->save();

        $client_contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $client->setRelation('contacts', $client_contact);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->partial = 5.0;
        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->company->setRelation('company', $this->company);
        $this->invoice->company->setRelation('client', $client);
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();
        $this->invoice->is_deleted = false;
        $this->invoice->save();

        $data = [
            'amount' => 6.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 6.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $invoice = $payment->invoices()->first();

        $this->assertEquals($invoice->partial, 0);
        $this->assertEquals($invoice->balance, 4);
    }

    public function testPaymentLessThanPartialAmount()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'send_email' => true,
        ]);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->partial = 5.0;
        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments?include=invoices', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $invoice = $payment->invoices()->first();

        $this->assertEquals($invoice->partial, 3);
        $this->assertEquals($invoice->balance, 8);
    }

    public function testPaymentValidationAmount()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'send_email' => true,
        ]);

        $client->setRelation('contact', $contact);

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->partial = 5.0;
        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $this->invoice->setRelation('client', $client);

        $data = [
            'amount' => 1.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertTrue(array_key_exists('amount', $message));
        }
    }

    public function testPaymentChangesBalancesCorrectly()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertTrue(array_key_exists('amount', $message));
        }

        if ($response) {
            $response->assertStatus(200);

            $invoice = Invoice::find($this->decodePrimaryKey($this->invoice->hashed_id));

            $this->assertEquals($invoice->balance, 8);

            $payment = $invoice->payments()->first();

            $this->assertEquals($payment->applied, 2);
        }
    }

    public function testUpdatePaymentValidationWorks()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->amount = 10;
        $payment->client_id = $client->id;
        $payment->date = now();
        $payment->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/payments/'.$this->encodePrimaryKey($payment->id), $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertTrue(array_key_exists('invoices', $message));
        }

        if ($response) {
            $response->assertStatus(200);
        }
    }

    public function testUpdatePaymentValidationPasses()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->amount = 10;
        $payment->client_id = $client->id;
        $payment->date = now();
        $payment->number = $client->getNextPaymentNumber($client, $payment);
        $payment->save();

        $data = [
            'amount' => 10.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($this->invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/payments/'.$this->encodePrimaryKey($payment->id), $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            \Log::error(print_r($e->validator->getMessageBag(), 1));

            $this->assertTrue(array_key_exists('invoices', $message));
        }

        if ($response) {
            $response->assertStatus(200);
        }
    }

    public function testDoublePaymentTestWithInvalidAmounts()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 15.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($this->invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            \Log::error(print_r($e->validator->getMessageBag(), 1));
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals($payment->amount, 15);
        $this->assertEquals($payment->applied, 10);

        $this->invoice = null;
        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 15.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($this->invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/payments/'.$this->encodePrimaryKey($payment->id), $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertTrue(array_key_exists('invoices', $message));
        }
    }

    public function testDoublePaymentTestWithValidAmounts()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 20.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($this->invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals($payment->amount, 20);
        $this->assertEquals($payment->applied, 10);

        // $this->invoice = null;
        // $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id);//stub the company and user_id
        // $this->invoice->client_id = $client->id;

        // $this->invoice->line_items = $this->buildLineItems();
        // $this->invoice->uses_inclusive_taxes = false;

        // $this->invoice->save();

        // $this->invoice_calc = new InvoiceSum($this->invoice);
        // $this->invoice_calc->build();

        // $this->invoice = $this->invoice_calc->getInvoice();
        // $this->invoice->save();
        // $this->invoice->service()->markSent()->createInvitations()->save();

        // $data = [
        //     'amount' => 20.0,
        //     'client_id' => $this->encodePrimaryKey($client->id),
        //     'invoices' => [
        //             [
        //                 'invoice_id' => $this->encodePrimaryKey($this->invoice->id),
        //                 'amount' => 10,
        //             ]
        //         ],
        //     'date' => '2019/12/12',
        // ];

        // $response = false;

        // try {
        //     $response = $this->withHeaders([
        //         'X-API-SECRET' => config('ninja.api_secret'),
        //         'X-API-TOKEN' => $this->token,
        //     ])->put('/api/v1/payments/'.$this->encodePrimaryKey($payment->id), $data);
        // } catch (ValidationException $e) {
        //     $message = json_decode($e->validator->getMessageBag(), 1);
        //     \Log::error(print_r($e->validator->getMessageBag(), 1));

        //     $this->assertTrue(array_key_exists('invoices', $message));
        // }

        // $response->assertStatus(200);

        // $arr = $response->json();

        // $this->assertEquals(20, $arr['data']['applied']);
    }

    public function testStorePaymentWithNoAmountField()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $payment_id = $arr['data']['id'];

            $this->assertEquals($this->invoice->amount, $arr['data']['amount']);

            $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

            $this->assertNotNull($payment);
            $this->assertNotNull($payment->invoices());
            $this->assertEquals(1, $payment->invoices()->count());
        }
    }

    public function testStorePaymentWithZeroAmountField()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => 0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();
        $payment_id = $arr['data']['id'];
        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals(round($payment->amount, 2), $this->invoice->amount);

        $this->assertEquals(round($payment->applied, 2), $this->invoice->amount);
    }

    public function testPaymentForInvoicesFromDifferentClients()
    {
        $client1 = ClientFactory::create($this->company->id, $this->user->id);
        $client1->save();

        $client2 = ClientFactory::create($this->company->id, $this->user->id);
        $client2->save();

        $invoice1 = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice1->client_id = $client1->id;
        $invoice1->status_id = Invoice::STATUS_SENT;

        $invoice1->line_items = $this->buildLineItems();
        $invoice1->uses_inclusive_Taxes = false;

        $invoice1->save();

        $invoice_calc = new InvoiceSum($invoice1);
        $invoice_calc->build();

        $invoice1 = $invoice_calc->getInvoice();
        $invoice1->save();

        $invoice2 = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice2->client_id = $client2->id;
        $invoice2->status_id = Invoice::STATUS_SENT;

        $invoice2->line_items = $this->buildLineItems();
        $invoice2->uses_inclusive_Taxes = false;

        $invoice2->save();

        $invoice_calc = new InvoiceSum($invoice2);
        $invoice_calc->build();

        $invoice2 = $invoice_calc->getInvoice();
        $invoice2->save();

        $data = [
            'amount' => $invoice1->amount + $invoice2->amount,
            'client_id' => $client1->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice1->hashed_id,
                    'amount' => $invoice1->amount,
                ],
                [
                    'invoice_id' => $invoice2->hashed_id,
                    'amount' => $invoice2->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }
    }

    public function testPaymentWithSameInvoiceMultipleTimes()
    {
        $client1 = ClientFactory::create($this->company->id, $this->user->id);
        $client1->save();

        $invoice1 = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice1->client_id = $client1->id;
        $invoice1->status_id = Invoice::STATUS_SENT;

        $invoice1->line_items = $this->buildLineItems();
        $invoice1->uses_inclusive_Taxes = false;

        $invoice1->save();

        $invoice_calc = new InvoiceSum($invoice1);
        $invoice_calc->build();

        $invoice1 = $invoice_calc->getInvoice();
        $invoice1->save();

        $data = [
            'amount' => $invoice1->amount,
            'client_id' => $client1->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice1->hashed_id,
                    'amount' => 1,
                ],
                [
                    'invoice_id' => $invoice1->hashed_id,
                    'amount' => 1,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $this->assertNull($response);
    }

    public function testStorePaymentWithCredits()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();

        $credit = CreditFactory::create($this->company->id, $this->user->id);
        $credit->client_id = $client->id;
        $credit->status_id = Credit::STATUS_SENT;

        $credit->line_items = $this->buildLineItems();
        $credit->uses_inclusive_taxes = false;

        $credit->save();

        $credit_calc = new InvoiceSum($credit);
        $credit_calc->build();

        $credit = $this->credit_calc->getCredit();
        $credit->save(); //$10 credit

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $credit->id,
                    'amount' => 5,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $payment_id = $arr['data']['id'];

            $payment = Payment::find($this->decodePrimaryKey($payment_id))->first();

            $this->assertNotNull($payment);
            $this->assertNotNull($payment->invoices());
            $this->assertEquals(1, $payment->invoices()->count());
        }
    }

    public function testStorePaymentExchangeRate()
    {
        $settings = ClientSettings::defaults();
        $settings->currency_id = '2';

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->settings = $settings;
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $payment_id = $arr['data']['id'];

            $payment = Payment::find($this->decodePrimaryKey($payment_id))->first();

            // nlog($payment);

            $this->assertNotNull($payment);
            $this->assertNotNull($payment->invoices());
            $this->assertEquals(1, $payment->invoices()->count());
        }
    }

    public function testPaymentActionArchive()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 20.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($this->invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $data = [
            'ids' => [$this->encodePrimaryKey($payment->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertGreaterThan(0, $arr['data'][0]['archived_at']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertEquals(1, $arr['data'][0]['is_deleted']);
    }

    public function testDeleteRefundedPayment()
    {
        $this->invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $this->invoice->client_id = $client->id;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->product_key = 'test';
        $item->notes = 'test';
        $item->custom_value1 = '';
        $item->custom_value2 = '';
        $item->custom_value3 = '';
        $item->custom_value4 = '';

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->save();
        $this->invoice->service()->markSent()->createInvitations()->save();

        $this->assertEquals(10, $this->invoice->balance);
        $this->assertEquals(10, $this->invoice->client->fresh()->balance);

        $this->invoice->service()->markPaid()->save();

        $this->assertEquals(0, $this->invoice->balance);
        $this->assertEquals(0, $this->invoice->client->balance);

        $this->assertTrue($this->invoice->payments()->exists());

        $payment = $this->invoice->payments()->first();

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 10,
            'date' => '2021/12/12',
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 10,
                ],
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/refund', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(10, $this->invoice->fresh()->balance);
        $this->assertEquals(10, $this->invoice->fresh()->balance);

        $data = [
            'ids' => [$this->encodePrimaryKey($payment->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=delete', $data);

        $this->assertEquals(10, $this->invoice->fresh()->balance);
        $this->assertEquals(10, $this->invoice->fresh()->balance);
    }

    public function testUniquePaymentNumbers()
    {
        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $this->client->hashed_id,
            'date' => '2020/12/12',
            'number' => 'duplicate',
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $arr = $response->json();

        $response->assertStatus(200);

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        if ($response) {
            $response->assertStatus(302);
        }
    }
}
