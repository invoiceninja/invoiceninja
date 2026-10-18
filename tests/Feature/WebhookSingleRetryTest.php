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

use App\Jobs\Util\WebhookHandler;
use App\Jobs\Util\WebhookSingle;
use App\Models\Webhook;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class WebhookSingleRetryTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testConnectionFailureReleasesTheJobWithBackoff(): void
    {
        $job = $this->jobWithResponses([
            new ConnectException(
                'Connection timed out',
                new Request('POST', 'https://example.com/webhook'),
            ),
        ])->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased(10);
    }

    public function testHandlerDispatchesEachWebhookDeliveryAsAJob(): void
    {
        config(['ninja.environment' => 'selfhost']);

        Queue::fake();
        $this->createWebhook();

        (new WebhookHandler(
            Webhook::EVENT_CREATE_CLIENT,
            $this->client,
            $this->company,
        ))->handle();

        Queue::assertPushed(WebhookSingle::class, 1);
    }

    public function testHostedWebhookDeliveryUsesTheWebhooksQueue(): void
    {
        config(['ninja.environment' => 'hosted']);

        $job = new WebhookSingle(
            1,
            $this->client,
            $this->company->db,
        );

        $this->assertSame('webhooks', $job->queue);
        $this->assertSame(60, $job->timeout);
    }

    public function testTooManyRequestsResponseReleasesTheJobWithBackoff(): void
    {
        $job = $this->jobWithResponses([
            new Response(429),
        ])->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased(10);
    }

    #[DataProvider('permanentClientErrorProvider')]
    public function testPermanentClientErrorsAreNotRetried(int $status): void
    {
        $job = $this->jobWithResponses([
            new Response($status),
        ])->withFakeQueueInteractions();

        $job->handle();

        $job->assertNotReleased();
    }

    public function testTransientFailureIsNotReleasedAfterTheFinalAttempt(): void
    {
        $job = $this->jobWithResponses([
            new Response(429),
        ])->withFakeQueueInteractions();
        $job->job->attempts = $job->tries;

        $job->handle();

        $job->assertNotReleased();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function permanentClientErrorProvider(): iterable
    {
        yield 'unauthorized' => [401];
        yield 'forbidden' => [403];
        yield 'unprocessable entity' => [422];
    }

    /**
     * @param  array<int, Response|\Throwable>  $responses
     */
    private function jobWithResponses(array $responses): TestWebhookSingle
    {
        $webhook = $this->createWebhook();

        return new TestWebhookSingle(
            $webhook->id,
            $this->client,
            $this->company->db,
            $responses,
        );
    }

    private function createWebhook(): Webhook
    {
        $webhook = new Webhook([
            'target_url' => 'https://example.com/webhook',
            'format' => 'JSON',
            'event_id' => Webhook::EVENT_CREATE_CLIENT,
            'rest_method' => 'post',
            'headers' => [],
        ]);
        $webhook->company_id = $this->company->id;
        $webhook->user_id = $this->user->id;
        $webhook->save();

        return $webhook;
    }
}

class TestWebhookSingle extends WebhookSingle
{
    /**
     * @param  array<int, Response|\Throwable>  $responses
     */
    public function __construct(
        int $subscription_id,
        mixed $entity,
        string $db,
        private array $responses,
    ) {
        parent::__construct($subscription_id, $entity, $db);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 180, 3600];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function makeHttpClient(array $config): Client
    {
        $config['handler'] = HandlerStack::create(new MockHandler($this->responses));

        return new Client($config);
    }
}
