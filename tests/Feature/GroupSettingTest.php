<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Credit;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class GroupSettingTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    public function testAddGroupSettings()
    {
        $settings = new \stdClass;
        $settings->currency_id = 1;

        $data = [
            'name' => 'testX',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('testX', $arr['data']['name']);
        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testArchiveGroupSettings()
    {
        $settings = new \stdClass;
        $settings->currency_id = 1;

        $data = [
            'name' => 'testY',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $data = [
            'action' => 'archive',
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/group_settings/bulk', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }
}
