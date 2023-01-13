<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Scheduler;

use App\Export\CSV\ClientExport;
use App\Models\RecurringInvoice;
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutEvents;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\MockUnitData;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
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

          $this->withoutExceptionHandling();
    }

    /**
     *       'name' => ['bail', 'required', Rule::unique('schedulers')->where('company_id', auth()->user()->company()->id)],
            'is_paused' => 'bail|sometimes|boolean',
            'frequency_id' => 'bail|required|integer|digits_between:1,12',
            'next_run' => 'bail|required|date:Y-m-d',
            'template' => 'bail|required|string',
            'parameters' => 'bail|array',
     */

    public function testClientStatementGeneration()
    {
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => '2023-01-31',
            'template' => 'client_statement',
            'clients' => [],

        ];
    }

    public function testDeleteSchedule()
    {

        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=delete', $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=restore', $data)
        ->assertStatus(200);

    }  

    public function testRestoreSchedule()
    {

        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=archive', $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=restore', $data)
        ->assertStatus(200);

    }    

    public function testArchiveSchedule()
    {

        $data = [
            'ids' => [$this->scheduler->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers/bulk?action=archive', $data)
        ->assertStatus(200);

    }

    public function testSchedulerPost()
    {

        $data = [
            'name' => 'A different Name',
            'frequency_id' => 5,
            'next_run' => now()->addDays(2)->format('Y-m-d'),
            'template' =>'statement',
            'parameters' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
    }    

    public function testSchedulerPut()
    {

        $data = [
            'name' => 'A different Name',
            'frequency_id' => 5,
            'next_run' => now()->addDays(2)->format('Y-m-d'),
            'template' =>'statement',
            'parameters' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/task_schedulers/'.$this->scheduler->hashed_id, $data);

        $response->assertStatus(200);
    }    

    public function testSchedulerGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_schedulers');

        $response->assertStatus(200);
    }

    public function testSchedulerCreate()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_schedulers/create');

        $response->assertStatus(200);
    }


    // public function testSchedulerPut()
    // {
    //     $data = [
    //         'description' => $this->faker->firstName(),
    //     ];

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->put('/api/v1/task_schedulers/'.$this->encodePrimaryKey($this->task->id), $data);

    //     $response->assertStatus(200);
    // }



    // public function testSchedulerCantBeCreatedWithWrongData()
    // {
    //     $data = [
    //         'repeat_every' => Scheduler::DAILY,
    //         'job' => Scheduler::CREATE_CLIENT_REPORT,
    //         'date_key' => '123',
    //         'report_keys' => ['test'],
    //         'date_range' => 'all',
    //         // 'start_from' => '2022-01-01'
    //     ];

    //     $response = false;

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->post('/api/v1/task_scheduler/', $data);

    //     $response->assertSessionHasErrors();
    // }

    // public function testSchedulerCanBeUpdated()
    // {
    //     $response = $this->createScheduler();

    //     $arr = $response->json();
    //     $id = $arr['data']['id'];

    //     $scheduler = Scheduler::find($this->decodePrimaryKey($id));

    //     $updateData = [
    //         'start_from' => 1655934741,
    //     ];
    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->put('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id), $updateData);

    //     $responseData = $response->json();
    //     $this->assertEquals($updateData['start_from'], $responseData['data']['start_from']);
    // }

    // public function testSchedulerCanBeSeen()
    // {
    //     $response = $this->createScheduler();

    //     $arr = $response->json();
    //     $id = $arr['data']['id'];

    //     $scheduler = Scheduler::find($this->decodePrimaryKey($id));

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->get('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id));

    //     $arr = $response->json();
    //     $this->assertEquals('create_client_report', $arr['data']['action_name']);
    // }

    // public function testSchedulerJobCanBeUpdated()
    // {
    //     $response = $this->createScheduler();

    //     $arr = $response->json();
    //     $id = $arr['data']['id'];

    //     $scheduler = Scheduler::find($this->decodePrimaryKey($id));

    //     $this->assertSame('create_client_report', $scheduler->action_name);

    //     $updateData = [
    //         'job' => Scheduler::CREATE_CREDIT_REPORT,
    //         'date_range' => 'all',
    //         'report_keys' => ['test1'],
    //     ];

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->put('/api/v1/task_scheduler/'.$this->encodePrimaryKey($scheduler->id), $updateData);

    //     $updatedSchedulerJob = Scheduler::first()->action_name;
    //     $arr = $response->json();

    //     $this->assertSame('create_credit_report', $arr['data']['action_name']);
    // }

    // public function createScheduler()
    // {
    //     $data = [
    //         'repeat_every' => Scheduler::DAILY,
    //         'job' => Scheduler::CREATE_CLIENT_REPORT,
    //         'date_key' => '123',
    //         'report_keys' => ['test'],
    //         'date_range' => 'all',
    //         'start_from' => '2022-01-01',
    //     ];

    //     return $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->post('/api/v1/task_scheduler/', $data);
    // }
}
