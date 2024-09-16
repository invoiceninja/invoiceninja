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

use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * 
 */
class UnappliedPaymentDeleteTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testUnappliedPaymentDelete()
    {
        $data = [
            'amount' => 1000,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $payment_id = $arr['data']['id'];
            $payment = Payment::with('client')->find($this->decodePrimaryKey($payment_id));

            $this->assertEquals(1000, $payment->amount);
            $this->assertEquals(1000, $payment->client->paid_to_date);

            try {
                $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->delete('/api/v1/payments/'.$payment_id);
            } catch (ValidationException $e) {
                $message = json_decode($e->validator->getMessageBag(), 1);
                $this->assertNotNull($message);
            }

            $response->assertStatus(200);

            $this->assertEquals(0, $this->client->fresh()->paid_to_date);
        }
    }

    public function testUnappliedPaymentWithPaidInvoice()
    {
        $data = [
            'name' => 'A Nice Client',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $client_hash_id = $arr['data']['id'];
        $client = Client::find($this->decodePrimaryKey($client_hash_id));

        $this->assertEquals($client->balance, 0);
        $this->assertEquals($client->paid_to_date, 0);
        //create new invoice.

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $invoice = [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $client_hash_id,
            'line_items' => (array) $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $invoice = $invoice->service()->markSent()->save();

        $this->assertEquals(20, $invoice->balance);
        $this->assertEquals(20, $invoice->client->balance);

        $data = [
            'amount' => 30,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 20,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $data);


        $arr = $response->json();

        $payment_hashed_id = $arr['data']['id'];
        $payment = Payment::find($this->decodePrimaryKey($payment_hashed_id));

        $this->assertEquals(30, $payment->amount);
        $this->assertEquals(20, $payment->applied);

        $payment->service()->deletePayment();

        $payment->fresh();
        $invoice->fresh();

        $this->assertEquals(0, $client->fresh()->paid_to_date);
        $this->assertEquals(20, $client->fresh()->balance);
    }

    public function testRefundPartialPaymentDeletion()
    {
        $data = [
            'name' => 'A Nice Client',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $client_hash_id = $arr['data']['id'];
        $client = Client::find($this->decodePrimaryKey($client_hash_id));

        $this->assertEquals($client->balance, 0);
        $this->assertEquals($client->paid_to_date, 0);
        //create new invoice.

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $invoice = [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $client_hash_id,
            'line_items' => (array) $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $invoice = $invoice->service()->markSent()->save();

        $this->assertEquals(20, $invoice->balance);
        $this->assertEquals(20, $invoice->client->balance);

        $data = [
            'amount' => 50,
            'client_id' => $client->hashed_id,
            'date' => '2020/12/12',
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 20,
                ],
            ],
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
            'amount' => 20,
            'date' => '2020/12/12',
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 20,
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
        }

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(20, $arr['data']['refunded']);
        $this->assertEquals(Payment::STATUS_PARTIALLY_REFUNDED, $arr['data']['status_id']);
        $this->assertEquals(20, $payment->fresh()->refunded);

        $this->assertEquals(30, $client->fresh()->paid_to_date);
        $this->assertEquals(20, $client->fresh()->balance);

        $payment->fresh()->service()->deletePayment();

        $this->assertEquals(0, $client->fresh()->paid_to_date);
        $this->assertEquals(20, $client->fresh()->balance);
    }
}
