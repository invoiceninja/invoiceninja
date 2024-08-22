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

namespace Tests\Feature\Payments;

use App\Factory\InvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * @test
 */
class CreditPaymentTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockUnitData;

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

    public function testRegularPayment()
    {
        $invoice = Invoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name2 = '';

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();
        $invoice = $invoice_calc->getInvoice();
        $invoice->setRelation('client', $this->client);
        $invoice->setRelation('company', $this->company);
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 0,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);


        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals($payment->amount, 10);
        $this->assertEquals($payment->applied, 10);
    }

    public function testCreditPayments()
    {
        $invoice = Invoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name2 = '';

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();
        $invoice = $invoice_calc->getInvoice();
        $invoice->setRelation('client', $this->client);
        $invoice->setRelation('company', $this->company);
        $invoice->service()->markSent()->save();

        $credit = Credit::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $credit->line_items = $this->buildLineItems();
        $credit->uses_inclusive_taxes = false;
        $credit->discount = 0;
        $credit->tax_rate1 = 0;
        $credit->tax_name1 = '';
        $credit->tax_rate2 = 0;
        $credit->tax_name2 = '';

        // $invoice->save();
        $invoice_calc = new InvoiceSum($credit);
        $invoice_calc->build();
        $credit = $invoice_calc->getCredit();
        $credit->setRelation('client', $this->client);
        $credit->setRelation('company', $this->company);
        $credit->service()->markSent()->save();

        $data = [
            'amount' => 0,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 10,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $credit->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals($payment->amount, 5);
        $this->assertEquals($payment->applied, 5);
    }

    /*

    public function testDoublePaymentTestWithInvalidAmounts()
    {

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
        $this->invoice->service()->markSent()->save();

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
    */
}
