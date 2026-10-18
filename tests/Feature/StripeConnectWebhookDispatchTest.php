<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Modules\Admin\Jobs\Stripe\PaymentMethodWebhook;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StripeConnectWebhookDispatchTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        if(!class_exists(PaymentMethodWebhook::class) || config('ninja.testvars.travis') !== false){
            $this->markTestSkipped('PaymentMethodWebhook job does not exist');
        }
    }

    #[DataProvider('supportedEvents')]
    public function testSupportedEventDispatchesPaymentMethodWebhook(string $eventType, string $objectId): void
    {
        Queue::fake();
        config(['ninja.ninja_connect_secret' => 'whsec_test']);

        $payload = json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'account' => 'acct_connected',
            'type' => $eventType,
            'data' => [
                'object' => [
                    'id' => $objectId,
                    'object' => str_starts_with($eventType, 'customer.') ? 'customer' : 'payment_method',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

        $response = $this->call(
            'POST',
            '/api/connect',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );

        $response->assertOk();
        Queue::assertPushed(
            PaymentMethodWebhook::class,
            fn (PaymentMethodWebhook $job): bool => $job->event->type === $eventType
        );
    }

    public static function supportedEvents(): array
    {
        return [
            'detached' => ['payment_method.detached', 'pm_detached'],
            'updated' => ['payment_method.updated', 'pm_updated'],
            'automatically updated' => ['payment_method.automatically_updated', 'pm_automatic'],
            'customer deleted' => ['customer.deleted', 'cus_deleted'],
            'legacy ACH source updated' => ['customer.source.updated', 'ba_updated'],
            'SetupIntent succeeded' => ['setup_intent.succeeded', 'seti_succeeded'],
            'SetupIntent failed' => ['setup_intent.setup_failed', 'seti_failed'],
            'mandate updated' => ['mandate.updated', 'mandate_updated'],
        ];
    }

    public function testInvalidSignatureDoesNotDispatchJob(): void
    {
        Queue::fake();
        config(['ninja.ninja_connect_secret' => 'whsec_test']);

        $response = $this->call(
            'POST',
            '/api/connect',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid',
            ],
            '{"id":"evt_invalid"}'
        );

        $response->assertBadRequest();
        Queue::assertNotPushed(PaymentMethodWebhook::class);
    }
}
