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
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\WebhookController
 */
class WebhookAPITest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        $this->makeTestData();

        $this->withoutExceptionHandling();
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
