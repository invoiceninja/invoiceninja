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

use App\Models\Design;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\DesignController
 */
class DesignApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $id;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testDesignPost()
    {
        $design = [
            'body' => 'body',
            'includes' => 'includes',
            'product' => 'product',
            'task' => 'task',
            'footer' => 'footer',
            'header' => 'header',
        ];

        $data = [
            'name' => $this->faker->firstName(),
            'design' => $design,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/designs', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->id = $arr['data']['id'];

        $this->assertEquals($data['name'], $arr['data']['name']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs');

        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs/'.$this->id);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($this->id, $arr['data']['id']);

        $data = [
            'name' => $this->faker->firstName(),
            'design' => $design,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/designs/'.$this->id, $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($data['name'], $arr['data']['name']);
        $this->assertEquals($data['design'], $arr['data']['design']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/designs/'.$this->id, $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $design = Design::whereId($this->decodePrimaryKey($this->id))->withTrashed()->first();

        $this->assertTrue((bool) $design->is_deleted);
        $this->assertGreaterThan(0, $design->deleted_at);
    }

    public function testDesignArchive()
    {
        $design = [
            'body' => 'body',
            'includes' => 'includes',
            'product' => 'product',
            'task' => 'task',
            'footer' => 'footer',
            'header' => 'header',
        ];

        $data = [
            'name' => $this->faker->firstName(),
            'design' => $design,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/designs', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->id = $arr['data']['id'];

        $data['ids'][] = $arr['data']['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/designs/bulk?action=archive', $data);

        $response->assertStatus(200);

        $design = Design::where('id', $this->decodePrimaryKey($arr['data']['id']))->withTrashed()->first();

        $this->assertNotNull($design->deleted_at);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/designs/bulk?action=restore', $data);

        $response->assertStatus(200);

        $design = Design::where('id', $this->decodePrimaryKey($arr['data']['id']))->withTrashed()->first();

        $this->assertNull($design->deleted_at);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/designs/bulk?action=delete', $data);

        $response->assertStatus(200);

        $design = Design::where('id', $this->decodePrimaryKey($arr['data']['id']))->withTrashed()->first();

        $this->assertTrue((bool) $design->is_deleted);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/designs/bulk?action=restore', $data);

        $response->assertStatus(200);

        $design = Design::where('id', $this->decodePrimaryKey($arr['data']['id']))->withTrashed()->first();

        $this->assertFalse((bool) $design->is_deleted);
        $this->assertNull($design->deleted_at);
    }
}
