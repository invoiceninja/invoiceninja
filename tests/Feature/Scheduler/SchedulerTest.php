<?php

namespace Tests\Feature\Scheduler;

use App\Export\CSV\ClientExport;
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutEvents;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockUnitData;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use MakesHash;
    use MockUnitData;
    use WithoutEvents;
    // use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        //   $this->withoutExceptionHandling();
    }

    public function testSchedulerCantBeCreatedWithWrongData()
    {
        $data = [
            'repeat_every' => Scheduler::DAILY,
            'job' => Scheduler::CREATE_CLIENT_REPORT,
            'date_key' => '123',
            'report_keys' => ['test'],
            'date_range' => 'all',
            // 'start_from' => '2022-01-01'
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/task_scheduler/', $data);

        $response->assertSessionHasErrors();
    }

    public function testSchedulerCanBeUpdated()
    {
        $response = $this->createScheduler();

        $arr = $response->json();
        $id = $arr['data']['id'];

        $scheduler = Scheduler::find($this->decodePrimaryKey($id));

        $updateData = [
            'start_from' => 1655934741,
        ];
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id), $updateData);

        $responseData = $response->json();
        $this->assertEquals($updateData['start_from'], $responseData['data']['start_from']);
    }

    public function testSchedulerCanBeSeen()
    {
        $response = $this->createScheduler();

        $arr = $response->json();
        $id = $arr['data']['id'];

        $scheduler = Scheduler::find($this->decodePrimaryKey($id));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id));

        $arr = $response->json();
        $this->assertEquals('create_client_report', $arr['data']['action_name']);
    }

    public function testSchedulerJobCanBeUpdated()
    {
        $response = $this->createScheduler();

        $arr = $response->json();
        $id = $arr['data']['id'];

        $scheduler = Scheduler::find($this->decodePrimaryKey($id));

        $this->assertSame('create_client_report', $scheduler->action_name);

        $updateData = [
            'job' => Scheduler::CREATE_CREDIT_REPORT,
            'date_range' => 'all',
            'report_keys' => ['test1'],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id), $updateData);

        $updatedSchedulerJob = Scheduler::first()->action_name;
        $arr = $response->json();

        $this->assertSame('create_credit_report', $arr['data']['action_name']);
    }

    public function createScheduler()
    {
        $data = [
            'repeat_every' => Scheduler::DAILY,
            'job' => Scheduler::CREATE_CLIENT_REPORT,
            'date_key' => '123',
            'report_keys' => ['test'],
            'date_range' => 'all',
            'start_from' => '2022-01-01',
        ];

        return $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/task_scheduler/', $data);
    }
}
