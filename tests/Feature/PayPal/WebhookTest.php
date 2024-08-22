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

namespace Tests\Feature\PayPal;

use stdClass;
use Tests\TestCase;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use Illuminate\Support\Str;
use App\Models\CompanyGateway;
use App\DataMapper\FeesAndLimits;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class WebhookTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private $webhook_string = '{"id":"WH-8WP702374D398111T-81807959NA3371206","event_version":"1.0","create_time":"2023-12-13T08:36:03.961Z","resource_type":"checkout-order","resource_version":"2.0","event_type":"CHECKOUT.ORDER.COMPLETED","summary":"Checkout Order Completed","resource":{"update_time":"2023-12-13T08:35:27Z","create_time":"2023-12-13T08:35:18Z","purchase_units":[{"reference_id":"default","amount":{"currency_code":"USD","value":"1285.13","breakdown":{"item_total":{"currency_code":"USD","value":"1285.13"}}},"payee":{"merchant_id":"KDCGGYWFNWTAN"},"payment_instruction":{"disbursement_mode":"INSTANT"},"description":"Invoice Number# fq30028","custom_id":"xLqrlFTUHJONFhSDhSUZBp0ckeZnpdFq","invoice_id":"fq30028","soft_descriptor":"NOVEMBER 6","items":[{"name":"Invoice Number# fq30028","unit_amount":{"currency_code":"USD","value":"1285.13"},"quantity":"1","description":"Ut totam facilis.Ut totam facilis.Ut totam facilis."}],"shipping":{"name":{"full_name":"John Doe"},"address":{"address_line_1":"1 Main St","admin_area_2":"San Jose","admin_area_1":"CA","postal_code":"95131","country_code":"US"}},"payments":{"captures":[{"id":"40A1323403146010F","status":"COMPLETED","amount":{"currency_code":"USD","value":"1285.13"},"final_capture":true,"disbursement_mode":"INSTANT","seller_protection":{"status":"ELIGIBLE","dispute_categories":["ITEM_NOT_RECEIVED","UNAUTHORIZED_TRANSACTION"]},"seller_receivable_breakdown":{"gross_amount":{"currency_code":"USD","value":"1285.13"},"paypal_fee":{"currency_code":"USD","value":"45.34"},"net_amount":{"currency_code":"USD","value":"1239.79"}},"invoice_id":"fq30028","custom_id":"xLqrlFTUHJONFhSDhSUZBp0ckeZnpdFq","links":[{"href":"https:\\/\\/api.sandbox.paypal.com\\/v2\\/payments\\/captures\\/40A1323403146010F","rel":"self","method":"GET"},{"href":"https:\\/\\/api.sandbox.paypal.com\\/v2\\/payments\\/captures\\/40A1323403146010F\\/refund","rel":"refund","method":"POST"},{"href":"https:\\/\\/api.sandbox.paypal.com\\/v2\\/checkout\\/orders\\/5WX67707S1265192L","rel":"up","method":"GET"}],"create_time":"2023-12-13T08:35:27Z","update_time":"2023-12-13T08:35:27Z"}]}}],"links":[{"href":"https:\\/\\/api.sandbox.paypal.com\\/v2\\/checkout\\/orders\\/5WX67707S1265192L","rel":"self","method":"GET"}],"id":"5WX67707S1265192L","payment_source":{"paypal":{"email_address":"sb-0kvkf26397832@personal.example.com","account_id":"4X5WHWAP5GQ3Y","account_status":"VERIFIED","name":{"given_name":"John","surname":"Doe"},"address":{"address_line_1":"62158","address_line_2":"341 Colton Canyon","admin_area_2":"Port Lisandro","admin_area_1":"New Jersey","postal_code":"08127","country_code":"GR"}}},"intent":"CAPTURE","payer":{"name":{"given_name":"John","surname":"Doe"},"email_address":"sb-0kvkf26397832@personal.example.com","payer_id":"4X5WHWAP5GQ3Y","address":{"address_line_1":"62158","address_line_2":"341 Colton Canyon","admin_area_2":"Port Lisandro","admin_area_1":"New Jersey","postal_code":"08127","country_code":"GR"}},"status":"COMPLETED"},"links":[{"href":"https:\\/\\/api.sandbox.paypal.com\\/v1\\/notifications\\/webhooks-events\\/WH-8WP702374D398111T-81807959NA3371206","rel":"self","method":"GET"},{"href":"https:\\/\\/api.sandbox.paypal.com\\/v1\\/notifications\\/webhooks-events\\/WH-8WP702374D398111T-81807959NA3371206\\/resend","rel":"resend","method":"POST"}],"q":"\\/api\\/v1\\/ppcp\\/webhook"}';

    private string $merchant_id = 'KDCGGYWFNWTAN';

    private string $invoice_number = 'fq30028';

    private float $amount = 1285.13;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function testWebhooks()
    {
        $hook = json_decode($this->webhook_string, true);
        $this->assertIsArray($hook);
    }

    public function testPaymentCreation()
    {
        $hook = json_decode($this->webhook_string, true);

        $company_gateway = $this->buildGateway();

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'number' => $this->invoice_number,
            'status_id' => 2,
            'amount' => $this->amount,
            'balance' => $this->amount,
        ]);

        $hash_data = [
            'invoices' => [
                    ['invoice_id' => $invoice->hashed_id, 'amount' => $this->amount],
                ],
            'credits' => 0,
            'amount_with_fee' => $this->amount,
            'pre_payment' => false,
            'frequency_id' => null,
            'remaining_cycles' => null,
            'is_recurring' => false,
        ];

        $payment_hash = new PaymentHash();
        $payment_hash->hash = Str::random(32);
        $payment_hash->data = $hash_data;
        $payment_hash->fee_total = 0;
        $payment_hash->fee_invoice_id = $invoice->id;
        $payment_hash->save();


        $driver = $company_gateway->driver($this->client);
        $driver->setPaymentHash($payment_hash);

        $source = 'paypal';
        $transaction_reference = $hook['resource']['purchase_units'][0]['payments']['captures'][0]['id'];
        $amount = $hook['resource']['purchase_units'][0]['payments']['captures'][0]['amount']['value'];

        $data = [
            'payment_type' => 3,
            'amount' => $amount,
            'transaction_reference' => $transaction_reference,
            'gateway_type_id' => GatewayType::PAYPAL,
        ];

        $payment = $driver->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

        $this->assertNotNull($payment);


    }

    private function buildGateway()
    {
        $config = new \stdClass();
        $config->merchantId = $this->merchant_id;
        $config->status = 'activated';
        $config->consent = 'true';
        $config->emailVerified = 'true';
        $config->permissions = 'true';
        $config->returnMessage = 'true';
        $config->paymentsReceivable = 'Yes';

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = '80af24a6a691230bbec33e930ab40666';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt($config);
        $cg->save();

        $fees_and_limits = new stdClass();
        $fees_and_limits->{3} = new FeesAndLimits();

        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        return $cg;
    }
}
