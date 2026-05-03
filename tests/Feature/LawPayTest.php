<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\LawPayPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

class LawPayTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private CompanyGateway $company_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        // Ensure the LawPay gateway exists in the database
        if (!Gateway::find(66)) {
            $gateway = new Gateway();
            $gateway->id = 66;
            $gateway->name = 'LawPay';
            $gateway->key = 'f4lafbnygsmkflagbqp7zqnfpgeoekdn';
            $gateway->provider = 'LawPay';
            $gateway->is_offsite = false;
            $gateway->fields = json_encode([
                'publicKey' => '',
                'secretKey' => '',
                'testMode' => false,
            ]);
            $gateway->visible = 1;
            $gateway->default_gateway_type_id = 1;
            $gateway->save();
        }

        // Create a company gateway for testing
        $config = new \stdClass();
        $config->publicKey = 'test_public_key';
        $config->secretKey = 'test_secret_key';
        $config->testMode = true;

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = 'f4lafbnygsmkflagbqp7zqnfpgeoekdn';
        $this->company_gateway->config = encrypt(json_encode($config));
        $this->company_gateway->fees_and_limits = '';
        $this->company_gateway->save();
    }

    private function createGatewayToken(int $gateway_type_id, string $token): ClientGatewayToken
    {
        $cgt = new ClientGatewayToken();
        $cgt->company_id = $this->company->id;
        $cgt->client_id = $this->client->id;
        $cgt->company_gateway_id = $this->company_gateway->id;
        $cgt->gateway_type_id = $gateway_type_id;
        $cgt->token = $token;
        $cgt->gateway_customer_reference = 'cust_test';
        $cgt->meta = new \stdClass();
        $cgt->save();

        return $cgt;
    }

    public function testGatewayRegistration(): void
    {
        $gateway = Gateway::find(66);

        $this->assertNotNull($gateway);
        $this->assertEquals('LawPay', $gateway->name);
        $this->assertEquals('LawPay', $gateway->provider);
        $this->assertEquals('f4lafbnygsmkflagbqp7zqnfpgeoekdn', $gateway->key);
    }

    public function testGatewayMethods(): void
    {
        $gateway = Gateway::find(66);

        $methods = $gateway->getMethods();

        $this->assertArrayHasKey(GatewayType::CREDIT_CARD, $methods);
        $this->assertArrayHasKey(GatewayType::BANK_TRANSFER, $methods);
        $this->assertTrue($methods[GatewayType::CREDIT_CARD]['refund']);
        $this->assertTrue($methods[GatewayType::CREDIT_CARD]['token_billing']);
        $this->assertTrue($methods[GatewayType::BANK_TRANSFER]['refund']);
        $this->assertTrue($methods[GatewayType::BANK_TRANSFER]['token_billing']);
    }

    public function testDriverInstantiation(): void
    {
        $driver = $this->company_gateway->driver($this->client);

        $this->assertInstanceOf(LawPayPaymentDriver::class, $driver);
    }

    public function testGatewayTypes(): void
    {
        $driver = $this->company_gateway->driver($this->client);

        $types = $driver->gatewayTypes();

        $this->assertContains(GatewayType::CREDIT_CARD, $types);
        $this->assertContains(GatewayType::BANK_TRANSFER, $types);
    }

    public function testSetPaymentMethodCreditCard(): void
    {
        $driver = $this->company_gateway->driver($this->client);
        $driver->setPaymentMethod(GatewayType::CREDIT_CARD);

        $this->assertInstanceOf(\App\PaymentDrivers\LawPay\CreditCard::class, $driver->payment_method);
    }

    public function testSetPaymentMethodACH(): void
    {
        $driver = $this->company_gateway->driver($this->client);
        $driver->setPaymentMethod(GatewayType::BANK_TRANSFER);

        $this->assertInstanceOf(\App\PaymentDrivers\LawPay\ACH::class, $driver->payment_method);
    }

    public function testSystemLogType(): void
    {
        $this->assertEquals(328, SystemLog::TYPE_LAWPAY);
        $this->assertEquals(328, LawPayPaymentDriver::SYSTEM_LOG_TYPE);
    }

    public function testCompanyGatewayConsts(): void
    {
        $cg = new CompanyGateway();

        $this->assertArrayHasKey('f4lafbnygsmkflagbqp7zqnfpgeoekdn', $cg->gateway_consts);
        $this->assertEquals(328, $cg->gateway_consts['f4lafbnygsmkflagbqp7zqnfpgeoekdn']);
    }

    public function testConvertToGatewayAmount(): void
    {
        $driver = $this->company_gateway->driver($this->client);

        $this->assertEquals(50000, $driver->convertToGatewayAmount(500.00));
        $this->assertEquals(100, $driver->convertToGatewayAmount(1.00));
        $this->assertEquals(1, $driver->convertToGatewayAmount(0.01));
        $this->assertEquals(9999, $driver->convertToGatewayAmount(99.99));
    }

    public function testConvertFromGatewayAmount(): void
    {
        $driver = $this->company_gateway->driver($this->client);

        $this->assertEquals(500.00, $driver->convertFromGatewayAmount(50000));
        $this->assertEquals(1.00, $driver->convertFromGatewayAmount(100));
        $this->assertEquals(0.01, $driver->convertFromGatewayAmount(1));
        $this->assertEquals(99.99, $driver->convertFromGatewayAmount(9999));
    }

    public function testRefundWithVoid(): void
    {
        Http::fake([
            'api.8am.com/v1/transactions/*/void' => Http::response(['id' => 'txn_123', 'status' => 'VOIDED'], 200),
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'transaction_reference' => 'txn_123',
            'amount' => 100.00,
            'status_id' => Payment::STATUS_COMPLETED,
            'company_gateway_id' => $this->company_gateway->id,
        ]);

        $driver = $this->company_gateway->driver($this->client);
        $result = $driver->refund($payment, 100.00);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['voided']);
    }

    public function testRefundAfterSettlement(): void
    {
        Http::fake([
            'api.8am.com/v1/transactions/*/void' => Http::response(['error' => 'Cannot void settled transaction'], 422),
            'api.8am.com/v1/charges/*/refund' => Http::response(['id' => 'ref_456', 'status' => 'REFUNDED'], 200),
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'transaction_reference' => 'txn_456',
            'amount' => 50.00,
            'status_id' => Payment::STATUS_COMPLETED,
            'company_gateway_id' => $this->company_gateway->id,
        ]);

        $driver = $this->company_gateway->driver($this->client);
        $result = $driver->refund($payment, 50.00);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('voided', $result);
    }

    public function testTokenBillingSuccess(): void
    {
        Http::fake([
            'api.8am.com/v1/charges' => Http::response([
                'id' => 'ch_789',
                'status' => 'COMPLETED',
                'amount' => 10000,
            ], 200),
        ]);

        $payment_hash = PaymentHash::create([
            'hash' => \Illuminate\Support\Str::random(32),
            'data' => (object) [
                'amount_with_fee' => 100.00,
                'invoices' => [
                    (object) ['invoice_id' => $this->invoice->hashed_id, 'amount' => 100.00],
                ],
                'total' => (object) ['amount_with_fee' => 100.00, 'invoice_totals' => 100.00],
            ],
            'fee_total' => 0,
            'fee_invoice_id' => $this->invoice->id,
        ]);

        $cgt = $this->createGatewayToken(GatewayType::CREDIT_CARD, 'saved_method_token_123');

        $driver = $this->company_gateway->driver($this->client);
        $driver->setPaymentHash($payment_hash);
        $payment = $driver->tokenBilling($cgt, $payment_hash);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status_id);
        $this->assertEquals('ch_789', $payment->transaction_reference);
    }

    public function testTokenBillingACHPending(): void
    {
        Http::fake([
            'api.8am.com/v1/charges' => Http::response([
                'id' => 'ch_ach_101',
                'status' => 'AUTHORIZED',
                'amount' => 25000,
            ], 200),
        ]);

        $payment_hash = PaymentHash::create([
            'hash' => \Illuminate\Support\Str::random(32),
            'data' => (object) [
                'amount_with_fee' => 250.00,
                'invoices' => [
                    (object) ['invoice_id' => $this->invoice->hashed_id, 'amount' => 250.00],
                ],
                'total' => (object) ['amount_with_fee' => 250.00, 'invoice_totals' => 250.00],
            ],
            'fee_total' => 0,
            'fee_invoice_id' => $this->invoice->id,
        ]);

        $cgt = $this->createGatewayToken(GatewayType::BANK_TRANSFER, 'saved_ach_token_456');

        $driver = $this->company_gateway->driver($this->client);
        $driver->setPaymentHash($payment_hash);
        $payment = $driver->tokenBilling($cgt, $payment_hash);

        $this->assertInstanceOf(Payment::class, $payment);
        // ACH should be PENDING, not COMPLETED
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status_id);
    }

    public function testTokenBillingFailure(): void
    {
        Http::fake([
            'api.8am.com/v1/charges' => Http::response([
                'code' => 'card_declined',
                'message' => 'The card was declined.',
            ], 422),
        ]);

        $payment_hash = PaymentHash::create([
            'hash' => \Illuminate\Support\Str::random(32),
            'data' => (object) [
                'amount_with_fee' => 100.00,
                'invoices' => [
                    (object) ['invoice_id' => $this->invoice->hashed_id, 'amount' => 100.00],
                ],
                'total' => (object) ['amount_with_fee' => 100.00, 'invoice_totals' => 100.00],
            ],
            'fee_total' => 0,
            'fee_invoice_id' => $this->invoice->id,
        ]);

        $cgt = $this->createGatewayToken(GatewayType::CREDIT_CARD, 'expired_token_789');

        $driver = $this->company_gateway->driver($this->client);
        $driver->setPaymentHash($payment_hash);

        $this->expectException(\App\Exceptions\PaymentFailed::class);
        $driver->tokenBilling($cgt, $payment_hash);
    }

    public function testWebhookCompletesACHPayment(): void
    {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'transaction_reference' => 'ch_webhook_test',
            'amount' => 75.00,
            'status_id' => Payment::STATUS_PENDING,
            'company_gateway_id' => $this->company_gateway->id,
        ]);

        $job = new \App\PaymentDrivers\LawPay\Jobs\LawPayWebhook(
            [
                'id' => 'ch_webhook_test',
                'status' => 'COMPLETED',
                'event' => 'charge.completed',
            ],
            $this->company->company_key,
            $this->company_gateway->id,
        );

        $job->handle();

        $payment->refresh();
        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status_id);
    }

    public function testWebhookFailsACHPayment(): void
    {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'transaction_reference' => 'ch_webhook_fail',
            'amount' => 50.00,
            'status_id' => Payment::STATUS_PENDING,
            'company_gateway_id' => $this->company_gateway->id,
        ]);

        $job = new \App\PaymentDrivers\LawPay\Jobs\LawPayWebhook(
            [
                'id' => 'ch_webhook_fail',
                'status' => 'returned',
                'event' => 'charge.returned',
            ],
            $this->company->company_key,
            $this->company_gateway->id,
        );

        $job->handle();

        $payment->refresh();
        $this->assertEquals(Payment::STATUS_FAILED, $payment->status_id);
    }

    public function testBaseUrl(): void
    {
        $driver = $this->company_gateway->driver($this->client);

        $this->assertEquals('https://api.8am.com', $driver->baseUrl());
    }

    public function testDriverProperties(): void
    {
        $driver = $this->company_gateway->driver($this->client);

        $this->assertTrue($driver->refundable);
        $this->assertTrue($driver->token_billing);
        $this->assertTrue($driver->can_authorise_credit_card);
    }
}
