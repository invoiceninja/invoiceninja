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

use App\Factory\DesignFactory;
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

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testFindInSetQueries()
    {

        $design = DesignFactory::create($this->company->id, $this->user->id);
        $design->is_template = true;
        $design->name = 'Test Template';
        $design->entities = 'searchable,payment,quote';
        $design->save();

        $searchable = 'searchable';

        $q = Design::query()
              ->where('is_template', true)
              ->where('company_id', $this->company->id)
              ->whereRaw('FIND_IN_SET( ? ,entities)', [$searchable]);

        $this->assertEquals(1, $q->count());

        $response = $this->withHeaders([
        'X-API-SECRET' => config('ninja.api_secret'),
        'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs?entities=payment');

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);

        $response = $this->withHeaders([
        'X-API-SECRET' => config('ninja.api_secret'),
        'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs?entities=,,,3,3,3,');

        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
        'X-API-SECRET' => config('ninja.api_secret'),
        'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs?entities=unsearchable');

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);

        $design = DesignFactory::create($this->company->id, $this->user->id);
        $design->is_template = true;
        $design->name = 'Test Template';
        $design->entities = 'searchable,payment,quote';
        $design->save();

        $searchable = 'unsearchable';

        $q = Design::query()
            ->where('is_template', true)
            ->whereRaw('FIND_IN_SET( ? ,entities)', [$searchable]);

        $this->assertEquals(0, $q->count());

        $design = DesignFactory::create($this->company->id, $this->user->id);
        $design->is_template = true;
        $design->name = 'Test Template';
        $design->entities = 'searchable,payment,quote';
        $design->save();

        $searchable = 'searchable,payment';

        $q = Design::query()
            ->where('is_template', true)
            ->whereRaw('FIND_IN_SET( ? ,entities)', [$searchable]);

        $this->assertEquals(0, $q->count());



    }

    public function testDesignTemplates()
    {
        $design = DesignFactory::create($this->company->id, $this->user->id);
        $design->is_template = true;
        $design->name = 'Test Template';
        $design->save();

        $response = $this->withHeaders([
          'X-API-SECRET' => config('ninja.api_secret'),
          'X-API-TOKEN' => $this->token,
          ])->get('/api/v1/designs?template=true');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(1, $arr['data']);
    }

    public function testDesignTemplatesExcluded()
    {
        $design = DesignFactory::create($this->company->id, $this->user->id);
        $design->is_template = true;
        $design->name = 'Test Template';
        $design->save();

        $response = $this->withHeaders([
          'X-API-SECRET' => config('ninja.api_secret'),
          'X-API-TOKEN' => $this->token,
          ])->get('/api/v1/designs?template=false');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(11, $arr['data']);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                ])->get('/api/v1/designs');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(12, $arr['data']);


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
