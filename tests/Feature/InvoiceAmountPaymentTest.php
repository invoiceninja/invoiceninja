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
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\BaseDriver;
use App\Repositories\PaymentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 */
class InvoiceAmountPaymentTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testPaymentAmountForInvoice()
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
        ])->post('/api/v1/invoices?amount_paid=10', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $this->assertEquals(10, $invoice->balance);
        $this->assertTrue($invoice->payments()->exists());

        $payment = $invoice->payments()->first();

        $this->assertEquals(10, $payment->applied);
        $this->assertEquals(10, $payment->amount);
    }

    public function testMarkPaidRemovesUnpaidGatewayFees()
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

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 5;
        $item->type_id = '3';

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
        ])->postJson('/api/v1/invoices?mark_sent=true', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $line_items = (array)$invoice->line_items;
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 5;
        $item->type_id = '3';

        $line_items[] = $item;

        $invoice->line_items = $line_items;
        $invoice->calc()->getInvoice();

        $this->assertEquals(25, $invoice->balance);
        $this->assertEquals(25, $invoice->amount);

        $invoice = $invoice->service()->markPaid()->save();

        $invoice->fresh();

        $this->assertEquals(20, $invoice->amount);
        $this->assertEquals(0, $invoice->balance);
    }

    public function testGatewayPaymentWithFeeAppliesToPartiallyPaidInvoice(): void
    {
        $line_item = InvoiceItemFactory::create();
        $line_item->quantity = 1;
        $line_item->cost = 100;

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->line_items = [$line_item];
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();
        $invoice = $invoice->service()->markSent()->save();

        app(PaymentRepository::class)->save([
            'amount' => 40,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 40,
                ],
            ],
        ], PaymentFactory::create($this->company->id, $this->user->id, $this->client->id));

        $invoice = $invoice->fresh();

        $this->assertEquals(Invoice::STATUS_PARTIAL, $invoice->status_id);
        $this->assertSame(100.0, $this->roundAmount($invoice->amount));
        $this->assertSame(60.0, $this->roundAmount($invoice->balance));
        $this->assertSame(40.0, $this->roundAmount($invoice->paid_to_date));

        $company_gateway = new CompanyGateway();
        $company_gateway->company_id = $this->company->id;
        $company_gateway->user_id = $this->user->id;
        $company_gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $company_gateway->accepted_credit_cards = 1;
        $company_gateway->require_cvv = true;
        $company_gateway->require_billing_address = true;
        $company_gateway->require_shipping_address = true;
        $company_gateway->update_details = true;
        $company_gateway->config = encrypt(json_encode(new \stdClass()));
        $company_gateway->fees_and_limits = [
            GatewayType::CREDIT_CARD => [
                'min_limit' => -1,
                'max_limit' => -1,
                'fee_amount' => 5,
                'fee_percent' => 0,
                'fee_tax_name1' => '',
                'fee_tax_rate1' => 0,
                'fee_tax_name2' => '',
                'fee_tax_rate2' => 0,
                'fee_tax_name3' => '',
                'fee_tax_rate3' => 0,
                'fee_cap' => 0,
                'adjust_fee_percent' => false,
                'is_enabled' => true,
            ],
        ];
        $company_gateway->save();

        $payment_hash_string = Str::random(32);

        $invoice->service()
            ->addGatewayFee($company_gateway, GatewayType::CREDIT_CARD, 60, $payment_hash_string)
            ->save();

        $invoice = $invoice->fresh();
        $unpaid_gateway_fee = collect($invoice->line_items)->firstWhere('unit_code', $payment_hash_string);

        $this->assertNotNull($unpaid_gateway_fee);
        $this->assertSame('3', (string) $unpaid_gateway_fee->type_id);
        $this->assertSame(105.0, $this->roundAmount($invoice->amount));
        $this->assertSame(65.0, $this->roundAmount($invoice->balance));
        $this->assertSame(40.0, $this->roundAmount($invoice->paid_to_date));

        $payment_hash = PaymentHash::create([
            'hash' => $payment_hash_string,
            'fee_total' => 5,
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'invoices' => [
                    [
                        'invoice_id' => $invoice->hashed_id,
                        'amount' => 60,
                        'due_date' => '',
                        'invoice_number' => $invoice->number,
                        'additional_info' => '',
                    ],
                ],
                'credits' => 0,
                'amount_with_fee' => 65,
            ],
        ]);

        $payment = (new BaseDriver($company_gateway, $this->client))
            ->setPaymentHash($payment_hash)
            ->createPayment([
                'amount' => 65,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type' => PaymentType::VISA,
                'transaction_reference' => 'txn_partial_gateway_fee_' . Str::random(12),
            ]);

        $invoice = $invoice->fresh();
        $payment = $payment->fresh();
        $paid_gateway_fee = collect($invoice->line_items)->firstWhere('unit_code', $payment_hash_string);
        $payment_invoice = $payment->invoices()->first();

        $this->assertNotNull($paid_gateway_fee);
        $this->assertSame('4', (string) $paid_gateway_fee->type_id);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status_id);
        $this->assertSame(105.0, $this->roundAmount($invoice->amount));
        $this->assertSame(0.0, $this->roundAmount($invoice->balance));
        $this->assertSame(105.0, $this->roundAmount($invoice->paid_to_date));
        $this->assertSame(65.0, $this->roundAmount($payment->amount));
        $this->assertSame(65.0, $this->roundAmount($payment->applied));
        $this->assertSame(65.0, $this->roundAmount($payment_invoice->pivot->amount));
    }

    private function roundAmount(mixed $amount): float
    {
        return round((float) $amount, 2);
    }
}
