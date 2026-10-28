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

    public function testWebhookIsRejectedWhenSecretIsNotConfigured(): void
    {
        $response = $this->driver(['webhookSecret' => ''])->processWebhookRequest($this->webhook('anything'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testWebhookIsRejectedWithWrongSecret(): void
    {
        $response = $this->driver()->processWebhookRequest($this->webhook('wrong-secret'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testWebhookWithValidSecretIgnoresUnknownEvents(): void
    {
        $response = $this->driver()->processWebhookRequest($this->webhook('webhook-secret', ['EventType' => 'SOMETHING_ELSE']));

        $this->assertSame(200, $response->getStatusCode());
    }

    private function driver(array $config = []): DeluxePaymentPlatformPaymentDriver
    {
        $gateway = new CompanyGateway();
        $gateway->id = 1;
        $gateway->setConfig(array_merge([
            'partnerToken' => 'partner-token',
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'webhookSecret' => 'webhook-secret',
            'testMode' => true,
        ], $config));

        return new DeluxePaymentPlatformPaymentDriver($gateway);
    }

    private function webhook(string $secret, array $payload = ['EventType' => 'TRANSACTION']): HttpRequest
    {
        return HttpRequest::create(
            '/payment_webhook/company/gateway?secret='.urlencode($secret),
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
    }
}
