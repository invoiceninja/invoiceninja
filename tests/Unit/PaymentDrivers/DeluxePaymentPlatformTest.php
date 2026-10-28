<?php

namespace Tests\Unit\PaymentDrivers;

use App\Models\CompanyGateway;
use App\PaymentDrivers\DeluxePaymentPlatformPaymentDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeluxePaymentPlatformTest extends TestCase
{
    public function testAuthRequestsSandboxClientCredentialsToken(): void
    {
        Http::fake(['*' => Http::response(['access_token' => 'bearer-token'], 200)]);

        $this->assertSame('ok', $this->driver()->auth());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://sandbox.api.deluxe.com/secservices/oauth2/v2/token'
                && $request['grant_type'] === 'client_credentials'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'));
        });
    }

    public function testAuthUsesProductionTokenUrlOutsideTestMode(): void
    {
        Http::fake(['*' => Http::response(['access_token' => 'bearer-token'], 200)]);

        $this->assertSame('ok', $this->driver(['testMode' => false])->auth());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.deluxe.com/secservices/oauth2/v2/token');
    }

    public function testAuthFailsWhenDeluxeRejectsCredentials(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->assertNotSame('ok', $this->driver()->auth());
    }

    public function testAuthFailsWithoutCredentials(): void
    {
        Http::fake();

        $this->assertSame('error', $this->driver(['clientSecret' => ''])->auth());

        Http::assertNothingSent();
    }

    public function testWebhookIgnoresUnknownEventsWithoutCallingDeluxe(): void
    {
        Http::fake();

        $response = $this->driver()->processWebhookRequest($this->webhook(['EventType' => 'SOMETHING_ELSE', 'TransactionId' => 'abc']));

        $this->assertSame(200, $response->getStatusCode());
        Http::assertNothingSent();
    }

    public function testWebhookVerifiesTransactionWithDeluxeBeforeRecording(): void
    {
        Http::fake([
            '*/oauth2/v2/token' => Http::response(['access_token' => 'bearer-token'], 200),
            '*/payments/search' => Http::response(['data' => ['payments' => []]], 200),
        ]);

        $response = $this->driver()->processWebhookRequest($this->webhook([
            'EventType' => 'Transaction',
            'TransactionId' => 'forged-transaction',
            'Status' => 'APPROVED',
            'TransactionAmount' => '100.00',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Transaction not found', $response->getData(true)['message']);

        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/dpp/v1/payments/search')
                && $request['paymentId'] === 'forged-transaction'
                && $request->hasHeader('Authorization', 'Bearer bearer-token')
                && $request->hasHeader('partnerToken', 'partner-token');
        });
    }

    public function testTransactionStatusMapping(): void
    {
        $driver = $this->driver();

        $this->assertSame('completed', $driver->transactionStatus(['isAch' => false, 'payment' => ['isSuccessful' => true, 'authResponse' => 'APPROVED 123456']]));
        $this->assertSame('failed', $driver->transactionStatus(['isAch' => false, 'payment' => ['isSuccessful' => false, 'authResponse' => 'DECLINED']]));
        $this->assertSame('pending', $driver->transactionStatus(['isAch' => true, 'payment' => ['isSuccessful' => false, 'authResponse' => 'PENDING']]));
        $this->assertSame('completed', $driver->transactionStatus(['isAch' => true, 'payment' => ['isSuccessful' => false, 'settled' => true, 'authResponse' => 'PENDING']]));
        $this->assertSame('failed', $driver->transactionStatus(['isAch' => true, 'payment' => ['isSuccessful' => false, 'authResponse' => 'REJECTED']]));
    }

    private function driver(array $config = []): DeluxePaymentPlatformPaymentDriver
    {
        $gateway = new CompanyGateway();
        $gateway->id = 1;
        $gateway->setConfig(array_merge([
            'partnerToken' => 'partner-token',
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'testMode' => true,
        ], $config));

        return new DeluxePaymentPlatformPaymentDriver($gateway);
    }

    private function webhook(array $payload): HttpRequest
    {
        return HttpRequest::create('/payment_webhook/company/gateway', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
    }
}
