<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\TaskController
 */
class TaskApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testTaskPost()
    {
        $data = [
            'description' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testTaskPostWithActionStart()
    {
        $data = [
            'description' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks?action=start', $data);
        
        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertGreaterThan(0, $arr['data']['start_time']);
    }

    public function testTaskPut()
    {
        $data = [
            'description' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), $data);

        $response->assertStatus(200);
    }


    public function testTasksGet()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/tasks');

        $response->assertStatus(200);
    }


    public function testTaskGet()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $response->assertStatus(200);
    }

    public function testTaskNotArchived()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testTaskArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testTaskRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testTaskDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}
