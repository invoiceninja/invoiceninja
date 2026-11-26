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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\WebhookController
 */
class WebhookAPITest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    private const PRIVATE_URL = 'http://10.20.30.40:8080/hooks/invoice?version=1';
    private const PUBLIC_URL = 'https://1.1.1.1/hooks/invoice';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testSelfHostedWebhookAcceptsExactlyListedPrivateUrlOnCreateAndUpdate(): void
    {
        config(['ninja.environment' => 'selfhost', 'ninja.webhook_url_allowlist' => [self::PRIVATE_URL]]);
        $this->authenticateWebhookRequests();

        $payload = ['target_url' => self::PRIVATE_URL, 'event_id' => 1, 'format' => 'JSON'];
        $this->postJson('/api/v1/webhooks', $payload)->assertOk()->assertJsonPath('data.target_url', self::PRIVATE_URL);

        $public = $this->postJson('/api/v1/webhooks', ['target_url' => self::PUBLIC_URL] + $payload)->assertOk();
        $this->putJson('/api/v1/webhooks/'.$public->json('data.id'), $payload)
            ->assertOk()->assertJsonPath('data.target_url', self::PRIVATE_URL);
    }

    #[DataProvider('rejectedWebhookTargets')]
    public function testWebhookRejectsTargetOnCreateAndUpdate(string $environment, array $allowlist, string $target): void
    {
        config(['ninja.environment' => $environment, 'ninja.webhook_url_allowlist' => $allowlist]);
        $this->authenticateWebhookRequests();

        $payload = ['target_url' => self::PUBLIC_URL, 'event_id' => 1, 'format' => 'JSON'];
        $public = $this->postJson('/api/v1/webhooks', $payload)->assertOk();

        $payload['target_url'] = $target;
        $this->postJson('/api/v1/webhooks', $payload)->assertUnprocessable()->assertJsonValidationErrors('target_url');
        $this->putJson('/api/v1/webhooks/'.$public->json('data.id'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('target_url');
    }

    public static function rejectedWebhookTargets(): array
    {
        $approved = [self::PRIVATE_URL];
        $malformed = 'not a URL';
        $unsupported = 'ftp://10.20.30.40/hook';
        $unresolved = 'https://webhook.invalid/hook';
        $reserved = 'http://127.0.0.1/hook';

        return [
            'different scheme' => ['selfhost', $approved, 'https://10.20.30.40:8080/hooks/invoice?version=1'],
            'different port' => ['selfhost', $approved, 'http://10.20.30.40:8081/hooks/invoice?version=1'],
            'different path' => ['selfhost', $approved, 'http://10.20.30.40:8080/hooks/client?version=1'],
            'different query' => ['selfhost', $approved, 'http://10.20.30.40:8080/hooks/invoice?version=2'],
            'hosted' => ['hosted', $approved, self::PRIVATE_URL],
            'empty allowlist' => ['selfhost', [], self::PRIVATE_URL],
            'malformed URL' => ['selfhost', [$malformed], $malformed],
            'unsupported scheme' => ['selfhost', [$unsupported], $unsupported],
            'unresolved host' => ['selfhost', [$unresolved], $unresolved],
            'reserved address' => ['selfhost', [$reserved], $reserved],
        ];
    }

    private function authenticateWebhookRequests(): void
    {
        $this->withExceptionHandling();
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ]);
    }

    public function testWebhookRetry()
    {

        $data = [
            'target_url' => 'http://hook.com',
            'event_id' => 1, //create client
            'format' => 'JSON',
            'headers' => []
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/webhooks", $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $data = [
            'entity' => 'client',
            'entity_id' => $this->client->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/webhooks/".$arr['data']['id']."/retry", $data);

        $response->assertStatus(200);

    }

    public function testWebhookGetFilter()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/webhooks?filter=xx');

        $response->assertStatus(200);
    }

    public function testWebhookGetRoute()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/webhooks');

        $response->assertStatus(200);
    }

    public function testWebhookPostRoute()
    {
        $data = [
            'target_url' => 'http://hook.com',
            'event_id' => 1,
            'format' => 'JSON',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/webhooks', $data);

        $response->assertStatus(200);

        $data = [
            'target_url' => 'http://hook.com',
            'event_id' => 1,
            'rest_method' => 'post',
            'format' => 'JSON',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/webhooks', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(1, $arr['data']['event_id']);

        $data = [
            'target_url' => 'http://hook.com',
            'event_id' => 2,
            'format' => 'JSON',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/webhooks/'.$arr['data']['id'], $data);

        $response->assertStatus(200);


        $data = [
            'target_url' => 'http://hook.com',
            'event_id' => 2,
            'rest_method' => 'post',
            'format' => 'JSON',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/webhooks/'.$arr['data']['id'], $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, $arr['data']['event_id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/webhooks/'.$arr['data']['id']);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['archived_at']);

        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/webhooks/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/webhooks/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}
