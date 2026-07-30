<?php

namespace Tests\Unit\PaymentDrivers\Stripe;

use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\CompanyGateway;
use App\PaymentDrivers\Stripe\PaymentMethodSyncService;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class StripePaymentDriverWebhookTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    #[DataProvider('paymentMethodEvents')]
    public function testPaymentMethodEventsArePassedToSyncService(
        string $eventType,
        string $objectId,
        string $method,
        bool $automaticallyUpdated
    ): void {
        $gateway = $this->makeStripeGateway();
        $syncService = Mockery::mock(PaymentMethodSyncService::class);
        $syncService->shouldReceive($method)
            ->once()
            ->withArgs(function (...$arguments) use ($gateway, $objectId, $automaticallyUpdated): bool {
                if (
                    !$arguments[0] instanceof Collection
                    || !$arguments[0]->contains(fn (CompanyGateway $companyGateway): bool => $companyGateway->is($gateway))
                ) {
                    return false;
                }

                if (is_string($arguments[1])) {
                    return $arguments[1] === $objectId;
                }

                return data_get($arguments[1], 'id') === $objectId
                    && ($arguments[2] ?? false) === $automaticallyUpdated;
            });
        $this->app->instance(PaymentMethodSyncService::class, $syncService);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest($eventType, $objectId)
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public static function paymentMethodEvents(): array
    {
        return [
            'detached' => ['payment_method.detached', 'pm_detached', 'removePaymentMethod', false],
            'updated' => ['payment_method.updated', 'pm_updated', 'updatePaymentMethod', false],
            'automatically updated' => ['payment_method.automatically_updated', 'pm_automatic', 'updatePaymentMethod', true],
            'customer deleted' => ['customer.deleted', 'cus_deleted', 'removeCustomerPaymentMethods', false],
        ];
    }

    private function makeStripeGateway(): CompanyGateway
    {
        $gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $gateway->accepted_credit_cards = 0;
        $gateway->fees_and_limits = new \stdClass();
        $gateway->setConfig([
            'webhookSecret' => 'whsec_standard_test',
        ]);
        $gateway->save();

        return $gateway;
    }

    private function signedRequest(string $eventType, string $objectId): PaymentWebhookRequest
    {
        $payload = json_encode([
            'id' => 'evt_'.str_replace('.', '_', $eventType),
            'object' => 'event',
            'type' => $eventType,
            'data' => [
                'object' => [
                    'id' => $objectId,
                    'object' => str_starts_with($eventType, 'customer.') ? 'customer' : 'payment_method',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_standard_test');

        return PaymentWebhookRequest::create(
            '/api/v1/payment_webhook/test/test',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );
    }
}
