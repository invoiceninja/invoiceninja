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
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class GroupSettingTest extends TestCase
{
    use MakesHash;

    //use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
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
        $settings->currency_id = '1';

        $data = [
            'name' => 'testX',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('testX', $arr['data']['name']);
        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testArchiveGroupSettings()
    {
        $settings = new \stdClass;
        $settings->currency_id = '1';

        $data = [
            'name' => 'testY',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $data = [
            'action' => 'archive',
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings/bulk', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }
}
