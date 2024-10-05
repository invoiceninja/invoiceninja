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

use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\SystemLogController
 */
class SystemLogApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }


    public function testFilters()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs?type_id=3')
        ->assertStatus(200);
        ;
    }

    public function testSystemLogRoutes()
    {
        $sl = [
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->client->user_id,
            'log' => 'thelog',
            'category_id' => 1,
            'event_id' => 1,
            'type_id' => 1,
        ];

        SystemLog::create($sl);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue(count($arr['data']) >= 1);

        $hashed_id = $arr['data'][0]['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs/'.$hashed_id);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($hashed_id, $arr['data']['id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/system_logs/'.$hashed_id, $sl)->assertStatus(400);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/system_logs/'.$hashed_id)->assertStatus(400);
    }

    public function testStoreRouteFails()
    {
        $sl = [
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->client->user_id,
            'log' => 'thelog',
            'category_id' => 1,
            'event_id' => 1,
            'type_id' => 1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/system_logs', $sl)->assertStatus(400);
    }

    public function testCreateRouteFails()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs/create')->assertStatus(400);
    }
}
