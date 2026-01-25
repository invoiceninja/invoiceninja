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

use Tests\TestCase;
use App\Models\Scheduler;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 *
 *   App\Services\Scheduler\EmailRecord
 */
class ScheduleEntityTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testEmailRecordSchedulerWithTemplatesNoTemplatePropAsBlank()
    {
        $scheduler = Scheduler::make([
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
                'template' => "",
            ],
        ]);

        $scheduler->company_id = $this->invoice->company_id;
        $scheduler->user_id = $this->invoice->user_id;
        $scheduler->save();

        $this->assertNotNull($scheduler);
        $this->assertDatabaseHas('schedulers', [
            'id' => $scheduler->id
        ]);

        $scheduler->service()->runTask();

        $this->assertDatabaseMissing('schedulers', [
            'id' => $scheduler->id
        ]);

    }


    public function testEmailRecordSchedulerWithTemplatesNoTemplatePropAsNull()
    {
        $scheduler = Scheduler::make([
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
                'template' => null,
            ],
        ]);

        $scheduler->company_id = $this->invoice->company_id;
        $scheduler->user_id = $this->invoice->user_id;
        $scheduler->save();

        $this->assertNotNull($scheduler);
        $this->assertDatabaseHas('schedulers', [
            'id' => $scheduler->id
        ]);

        $scheduler->service()->runTask();

        $this->assertDatabaseMissing('schedulers', [
            'id' => $scheduler->id
        ]);

    }

    public function testEmailRecordSchedulerWithTemplatesNoTemplateDefined()
    {
        $scheduler = Scheduler::make([
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
            ],
        ]);

        $scheduler->company_id = $this->invoice->company_id;
        $scheduler->user_id = $this->invoice->user_id;
        $scheduler->save();

        $this->assertNotNull($scheduler);
        $this->assertDatabaseHas('schedulers', [
            'id' => $scheduler->id
        ]);

        $scheduler->service()->runTask();

        $this->assertDatabaseMissing('schedulers', [
            'id' => $scheduler->id
        ]);

    }

    public function testEmailRecordSchedulerWithTemplatesPurchaseOrder()
    {
        $scheduler = Scheduler::make([
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'purchase_order',
                'entity_id' => $this->purchase_order->hashed_id,
                'template' => 'purchase_order',
            ],
        ]);

        $scheduler->company_id = $this->purchase_order->company_id;
        $scheduler->user_id = $this->purchase_order->user_id;
        $scheduler->save();

        $this->assertNotNull($scheduler);
        $this->assertDatabaseHas('schedulers', [
            'id' => $scheduler->id
        ]);

        $scheduler->service()->runTask();

        $this->assertDatabaseMissing('schedulers', [
            'id' => $scheduler->id
        ]);

    }

    public function testEmailRecordSchedulerWithTemplatesCredit()
    {
        $scheduler = Scheduler::make([
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'credit',
                'entity_id' => $this->credit->hashed_id,
                'template' => 'credit',
            ],
        ]);

        $scheduler->company_id = $this->credit->company_id;
        $scheduler->user_id = $this->credit->user_id;
        $scheduler->save();

        $this->assertNotNull($scheduler);
        $this->assertDatabaseHas('schedulers', [
            'id' => $scheduler->id
        ]);

        $scheduler->service()->runTask();

        $this->assertDatabaseMissing('schedulers', [
            'id' => $scheduler->id
        ]);

    }

    public function testEmailRecordSchedulerWithTemplatesQuote()
    {
        $scheduler = Scheduler::make([
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'quote',
                'entity_id' => $this->quote->hashed_id,
                'template' => 'reminder1',
            ],
        ]);

        $scheduler->company_id = $this->quote->company_id;
        $scheduler->user_id = $this->quote->user_id;
        $scheduler->save();

        $this->assertNotNull($scheduler);
        $this->assertDatabaseHas('schedulers', [
            'id' => $scheduler->id
        ]);

        $scheduler->service()->runTask();

        $this->assertDatabaseMissing('schedulers', [
            'id' => $scheduler->id
        ]);

    }

    public function testEmailRecordSchedulerWithTemplates()
    {
        $scheduler = Scheduler::make([
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
                'template' => 'invoice',
            ],
        ]);

        $scheduler->company_id = $this->invoice->company_id;
        $scheduler->user_id = $this->invoice->user_id;
        $scheduler->save();

        $this->assertNotNull($scheduler);
        $this->assertDatabaseHas('schedulers', [
            'id' => $scheduler->id
        ]);

        $scheduler->service()->runTask();

        $this->assertDatabaseMissing('schedulers', [
            'id' => $scheduler->id
        ]);

    }

    public function testSchedulerStoreAndUpdateWithTemplate()
    {

        $data = [
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
                'template' => 'invoice',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $this->assertEquals('invoice', $response->json('data.parameters.template'));

        $schedule_id = $response->json('data.id');

        $data = [
            'name' => 'A test entity email scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'invoice',
                'entity_id' => $this->invoice->hashed_id,
                'template' => 'reminder1',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/task_schedulers/' . $schedule_id, $data);

        $response->assertStatus(200);

        $this->assertEquals('reminder1', $response->json('data.parameters.template'));
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
