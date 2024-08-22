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

use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Services\Scheduler\EmailRecord
 */
class ScheduleEntityTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    public $faker;

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
            'template' => 'email_record',
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
            'template' => 'email_record',
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
            'template' => 'email_record',
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
