<?php

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
    public function setUp() :void
    {

        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
        $this->withoutExceptionHandling();

    }

    public function testPaymentList()
    {


        factory(\App\Models\Client::class, 1)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id
            ]);

        });

        $client = Client::all()->first();

        factory(\App\Models\Payment::class, 1)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $client->id]);


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/payments');

        $response->assertStatus(200);

    }

    public function testPaymentRESTEndPoints()
    {

        factory(\App\Models\Payment::class, 1)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $Payment = Payment::all()->first();

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/payments/'.$this->encodePrimaryKey($Payment->id));

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

        $this->invoice = InvoiceFactory::create($this->company->id,$this->user->id);//stub the company and user_id
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
                $this->invoice
            ],
            'payment_date' => '2020/12/12',

        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/', $data);
        }
        catch(ValidationException $e) {

            $message = json_decode($e->validator->getMessageBag(),1);

            $this->assertTrue(array_key_exists('client_id', $message));

        }


    }

    public function testStorePaymentWithClientId()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $this->invoice = InvoiceFactory::create($this->company->id,$this->user->id);//stub the company and user_id
        $this->invoice->client_id = $client->id;
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();
        $this->invoice->id = $this->invoice->hashed_id;

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                $this->invoice
            ],
            'payment_date' => '2020/12/12',

        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        }
        catch(ValidationException $e) {

            $message = json_decode($e->validator->getMessageBag(),1);
            
            $this->assertTrue(array_key_exists('client_id', $message));

        }

        $arr = $response->json();

       \Log::error($arr);
        $response->assertStatus(200);

    }

    // public function testUpdatePayment()
    // {

    // }

}
