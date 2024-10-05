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

use App\Factory\InvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
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
class UpdatePaymentTest extends TestCase
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

        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testUpdatingPaymentableDates()
    {
        $this->invoice = $this->invoice->service()->markPaid()->save();

        $payment = $this->invoice->payments->first();

        $this->assertNotNull($payment);

        $payment->paymentables()->each(function ($pivot) {

            $this->assertTrue(Carbon::createFromTimestamp($pivot->created_at)->isToday());
        });

        $payment->paymentables()->each(function ($pivot) {

            $pivot->created_at = now()->startOfDay()->subMonth();
            $pivot->save();

        });

        $payment->paymentables()->each(function ($pivot) {

            $this->assertTrue(Carbon::createFromTimestamp($pivot->created_at)->eq(now()->startOfDay()->subMonth()));

        });




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

        $data = [
            'amount' => 10,
            'client_id' => $client->hashed_id,
        ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices,paymentables', $data)
        ->assertStatus(200);

        $this->assertEquals(10, $client->fresh()->paid_to_date);
    }
}
