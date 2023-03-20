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

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Client;
use App\Models\Scheduler;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Factory\SchedulerFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\DataMapper\Schedule\EmailStatement;
use App\Services\Scheduler\SchedulerService;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\WithoutEvents;
use App\Services\Scheduler\EmailStatementService;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 * @covers  App\Services\Scheduler\SchedulerEntity
 */
class ScheduleEntityTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use WithoutEvents;

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
    }

    public function testSchedulerStore()
    {

        $data = [
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'schedule_entity',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

    }


    public function testSchedulerStore2()
    {

        $data = [
            'name' => 'A test entity email scheduler',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'schedule_entity',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

    }

        public function testSchedulerStore4()
    {

        $data = [
            'name' => 'A test entity email scheduler',
            'next_run' => now()->format('Y-m-d'),
            'template' => 'schedule_entity',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

    }


}