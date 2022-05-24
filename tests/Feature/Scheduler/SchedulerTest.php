<?php


namespace Tests\Feature\Scheduler;

use App\Export\CSV\ClientExport;
use App\Models\ScheduledJob;
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutEvents;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockUnitData;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use MakesHash;
    use MockUnitData;
    use WithoutEvents;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();


        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testSchedulerCantBeCreatedWithWrongData()
    {
        $data = [
            'repeat_every' => Scheduler::DAILY,
            'job' => ScheduledJob::CREATE_CLIENT_REPORT,
            'date_key' => '123',
            'report_keys' => ['test'],
            // 'date_range' => 'all',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/task_scheduler/', $data);

        $response->assertSessionHasErrors();

    }

    public function testSchedulerCanBeUpdated()
    {
       $this->createScheduler();


        $scheduler = Scheduler::first();
        $updateData = [
            'start_from' => 1655934741
        ];
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/task_scheduler/' . $scheduler->id, $updateData);

        $responseData = $response->json();
        $this->assertEquals(['successfully_updated_scheduler'], $responseData);
    }

    public function testSchedulerCanBeSeen()
    {
        $this->createScheduler();


        $scheduler = Scheduler::first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_scheduler/' . $scheduler->id);

        $arr = $response->json();
        $this->assertEquals('create_client_report', $arr['data']['job']['action_name']);


    }

    public function testSchedulerCanBeDeleted()
    {
        $this->createScheduler();

        $scheduler = Scheduler::first();
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/task_scheduler/' . $scheduler->id);

        $this->assertEquals(0, Scheduler::count());

    }

    public function testSchedulerJobCanBeUpdated()
    {
        $this->createScheduler();

        $scheduler = Scheduler::first();
        $this->assertSame('create_client_report', $scheduler->job->action_name);

        $updateData = [
            'job' => ScheduledJob::CREATE_CREDIT_REPORT,
            'date_range' => 'all',
            'report_keys' => ['test1']
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/task_scheduler/' . $scheduler->id . '/update_job', $updateData);

        $updatedSchedulerJob = Scheduler::first()->job->action_name;
        $this->assertSame('create_credit_report', $updatedSchedulerJob);
    }

    public function testSchedulerCanBeCreated()
    {
        $response = $this->createScheduler();

        $all_schedulers = Scheduler::count();

        $this->assertSame(1, $all_schedulers);

        $response->assertStatus(200);

    }

    public function createScheduler()
    {
        $data = [
            'repeat_every' => Scheduler::DAILY,
            'job' => ScheduledJob::CREATE_CLIENT_REPORT,
            'date_key' => '123',
            'report_keys' => ['test'],
            'date_range' => 'all',
        ];

        return $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/task_scheduler/', $data);
    }
}
