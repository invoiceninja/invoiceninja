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

use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class GoCardlessInstantBankPaymentTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use MakesHash;

    private array $mock = [
  'events' =>
  [
    [
      'id' => 'EV032JF',
      'links' =>
      [
        'customer' => 'CU001ZDX',
        'billing_request' => 'BRQ0005',
        'billing_request_flow' => 'BRF0005S6VYV',
        'customer_bank_account' => 'BA001V2111PK6J',
      ],
      'action' => 'payer_details_confirmed',
      'details' =>
      [
        'cause' => 'billing_request_payer_details_confirmed',
        'origin' => 'api',
        'description' => 'Payer has confirmed all their details for this billing request.',
      ],
      'metadata' => [],
      'created_at' => '2022-11-06T08:50:32.641Z',
      'resource_type' => 'billing_requests',
    ],
    [
      'id' => 'EV032JF67TF2',
      'links' =>
      [
        'customer' => 'CU001DXYDR3',
        'billing_request' => 'BRQ005YJ7GHF',
        'customer_bank_account' => 'BA00V2111PK',
        'mandate_request_mandate' => 'MD01W5RP7GA',
      ],
      'action' => 'fulfilled',
      'details' =>
      [
        'cause' => 'billing_request_fulfilled',
        'origin' => 'api',
        'description' => 'This billing request has been fulfilled, and the resources have been created.',
      ],
      'metadata' => [],
      'created_at' => '2022-11-06T08:50:35.134Z',
      'resource_type' => 'billing_requests',
    ],
    [
      'id' => 'EV032JF67S0M8',
      'links' =>
      [
        'mandate' => 'MD001W5RP7GA1W',
      ],
      'action' => 'created',
      'details' =>
      [
        'cause' => 'mandate_created',
        'origin' => 'api',
        'description' => 'Mandate created via the API.',
      ],
      'metadata' =>
      [],
      'created_at' => '2022-11-06T08:50:34.667Z',
      'resource_type' => 'mandates',
    ],
  ],
];


    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testWebhookProcessingWithGoCardless()
    {
        $this->assertIsArray($this->mock);

        foreach ($this->mock['events'] as $event) {
            if ($event['action'] == 'fulfilled' && array_key_exists('billing_request', $event['links'])) {
                $this->assertEquals('CU001DXYDR3', $event['links']['customer']);
                $this->assertEquals('BRQ005YJ7GHF', $event['links']['billing_request']);
                $this->assertEquals('BA00V2111PK', $event['links']['customer_bank_account']);
            }
        }

        // mock the invoice and the payment hash
    }

    public function testInvoiceDelayedNotificationPayment()
    {
        $gocardlesspayment = new \stdClass();
        $links = new \stdClass();
        $links->mandate = "my_mandate";
        $gocardlesspayment->links = $links;
        $gocardlesspayment->id = "gocardless_payment_id";


        $invoice = Invoice::factory()->create(
            [
              'user_id' => $this->user->id,
              'company_id' => $this->company->id,
              'client_id' => $this->client->id
            ]
        );

        $invoice->status_id = Invoice::STATUS_SENT;
        $invoice->calc()->getInvoice()->save();


        $data_object = json_decode('{"invoices":[{"invoice_id":"xx","amount":0,"due_date":"","invoice_number":"0","additional_info":"2022-07-18"}],"credits":0,"amount_with_fee":15,"client_id":23,"billing_request":"BRQ005YJ7GHF","billing_request_flow":"xxdfdf"}');

        $invoice_object = end($data_object->invoices);
        $invoice_object->invoice_id = $invoice->hashed_id;
        $invoice_object->invoice_number = $invoice->number;
        $invoice_object->amount = $invoice->amount;
        $data_object->invoices = [$invoice_object];
        $data_object->client = $this->client->id;
        $data_object->amount_with_fee = $invoice->amount;

        $payment_hash = new PaymentHash();
        $payment_hash->hash = "1234567890abc";
        $payment_hash->fee_total = 0;
        $payment_hash->fee_invoice_id = $invoice->hashed_id;
        $payment_hash->data = $data_object;
        $payment_hash->save();


        $this->assertIsArray($data_object->invoices);
        $this->assertIsObject(end($data_object->invoices));
        $this->assertEquals(1, count($data_object->invoices));

        $test_invoice_object = end($data_object->invoices);

        $this->assertEquals($invoice->hashed_id, $test_invoice_object->invoice_id);
        $this->assertEquals($invoice->balance, $test_invoice_object->amount);


        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'b9886f9257f0c6ee7c302f1c74475f6c';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = '';
        $cg->save();

        foreach ($this->mock['events'] as $event) {
            if ($event['action'] == 'fulfilled' && array_key_exists('billing_request', $event['links'])) {
                $hash = PaymentHash::whereJsonContains('data->billing_request', $event['links']['billing_request'])->first();

                $this->assertNotNull($hash);
                $this->assertEquals('1234567890abc', $hash->hash);

                $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($hash->invoices(), 'invoice_id')))->withTrashed()->get();

                $this->assertNotNull($invoices);
                $this->assertEquals(1, $invoices->count());

                // remove all paid invoices
                $invoices->filter(function ($invoice) {
                    return $invoice->isPayable();
                });

                $this->assertEquals(1, $invoices->count());


                $data = [
                    'payment_method' => $gocardlesspayment->links->mandate,
                    'payment_type' => PaymentType::INSTANT_BANK_PAY,
                    'amount' => $hash->data->amount_with_fee,
                    'transaction_reference' => $gocardlesspayment->id,
                    'gateway_type_id' => GatewayType::INSTANT_BANK_PAY,
                ];


                $this->assertEquals('my_mandate', $data['payment_method']);
                $this->assertEquals('gocardless_payment_id', $data['transaction_reference']);
                $this->assertEquals($invoice->balance, $data['amount']);

                $gocardless_driver = $cg->driver($this->client);
                $gocardless_driver->setPaymentHash($hash);

                $payment = $gocardless_driver->createPayment($data, Payment::STATUS_COMPLETED);

                $this->assertInstanceOf(Payment::class, $payment);

                $this->assertEquals(round($invoice->amount, 2), round($payment->amount, 2));
                $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status_id);
                $this->assertEquals(1, $payment->invoices()->count());
                $this->assertEquals($invoice->number, $payment->invoices()->first()->number);
                $this->assertEquals(Invoice::STATUS_PAID, $payment->invoices()->first()->status_id);
            }
        }
    }
}
