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
class UpdatePaymentTest extends TestCase
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

        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testUpdatePaymentClientPaidToDate()
    {

        //Create new client
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->assertEquals(0, $client->balance);
        $this->assertEquals(0, $client->paid_to_date);

        //Create Invoice
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();
        $invoice = (new InvoiceSum($invoice))->build()->getInvoice();
        $invoice->save();

        $this->assertEquals(0, $invoice->balance);

        $invoice->service()->markSent()->save();

        $this->assertEquals(10, $invoice->balance);

        //create Unapplied payment via API

        // $data = [
        //     'amount' => $this->invoice->amount,
        //     'client_id' => $client->hashed_id,
        //     'invoices' => [
        //         [
        //         'invoice_id' => $this->invoice->hashed_id,
        //         'amount' => $this->invoice->amount,
        //         ],
        //     ],
        //     'date' => '2020/12/12',

        // ];

        $data = [
            'amount' => 10,
            'client_id' => $client->hashed_id,
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

        // $arr = $response->json();
        // $response->assertStatus(200);
        // $payment_id = $arr['data']['id'];
        // $payment = Payment::find($this->decodePrimaryKey($payment_id))->first();
        // $payment->load('invoices');

        // $this->assertNotNull($payment);
        // $this->assertNotNull($payment->invoices());
        // $this->assertEquals(1, $payment->invoices()->count());

        $this->assertEquals(10, $client->fresh()->paid_to_date);
    }
}
