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
use Illuminate\Validation\ValidationException;
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

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testStartTask()
    {
        $log = [
            [2, 1],
            [10, 20],
        ];

        $last = end($log);

        $this->assertEquals(10, $last[0]);
        $this->assertEquals(20, $last[1]);

        $new = [time(), 0];

        array_push($log, $new);

        $this->assertEquals(3, count($log));

        //test task is started
        $last = end($log);
        $this->assertTrue($last[1] === 0);

        //stop task
        $last = end($log);
        $last[1] = time();

        $this->assertTrue($last[1] !== 0);
    }

    public function testTaskPost()
    {
        $data = [
            'description' => $this->faker->firstName(),
            'number' => 'taskynumber',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals('taskynumber', $arr['data']['number']);
        $this->assertLessThan(5, strlen($arr['data']['time_log']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tasks/'.$arr['data']['id'], $data);

        $response->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks', $data);

            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }

        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testTaskPostNoDefinedTaskNumber()
    {
        $data = [
            'description' => $this->faker->firstName(),
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
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?action=start', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }

    public function testTaskPut()
    {
        $data = [
            'description' => $this->faker->firstName(),
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

    public function testTaskPostWithStartAction()
    {
        $data = [
            'description' => $this->faker->firstName(),
            'number' => 'taskynumber2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?start=true', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals('taskynumber2', $arr['data']['number']);
        $this->assertGreaterThan(5, strlen($arr['data']['time_log']));
    }

    public function testTaskPostWithStopAction()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?stop=true', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertLessThan(5, strlen($arr['data']['time_log']));
    }
}
