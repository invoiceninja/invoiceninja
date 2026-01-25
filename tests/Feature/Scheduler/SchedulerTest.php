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
use App\Models\Task;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Scheduler;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Factory\SchedulerFactory;
use App\Services\Scheduler\EmailReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\DataMapper\Schedule\EmailStatement;
use Illuminate\Validation\ValidationException;
use App\Services\Scheduler\EmailStatementService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\Scheduler\InvoiceOutstandingTasksService;
use App\Http\Requests\TaskScheduler\PaymentScheduleRequest;
use App\Utils\Traits\MakesDates;

/**
 *
 *   App\Services\Scheduler\SchedulerService
 */
class SchedulerTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;
    use MakesDates;
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


    public function testPaymentScheduleCalculationsIsPercentageWithAutoBill()
    {
        $settings = $this->company->settings;
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';
        $this->company->settings = $settings;
        $this->company->save();

        \App\Models\Credit::where('client_id', $this->client->id)->delete();

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 300.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);


        $data = [
           'name' => 'A test payment schedule scheduler',
           'frequency_id' => 0,
           'next_run' => now()->format('Y-m-d'),
           'template' => 'payment_schedule',
           'parameters' => [
               'invoice_id' => $invoice->hashed_id,
               'auto_bill' => true,
               'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 10,
                    'is_amount' => false,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 90,
                    'is_amount' => false,
                ]
               ],
           ],
       ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $scheduler = Scheduler::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertNotNull($scheduler);

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        $this->assertEquals(30, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), $invoice->partial_due_date->format('Y-m-d'));

        $scheduler = $scheduler->fresh();

        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));

        $this->travelTo(now()->addDays(30));

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        $this->assertEquals(300, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), $invoice->partial_due_date->format('Y-m-d'));

        $this->travelBack();
    }


    public function testPaymentScheduleCalculationsIsAmountWithAutoBill()
    {
        $settings = $this->company->settings;
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';
        $this->company->settings = $settings;
        $this->company->save();

        \App\Models\Credit::where('client_id', $this->client->id)->delete();

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 300.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);


        $data = [
           'name' => 'A test payment schedule scheduler',
           'frequency_id' => 0,
           'next_run' => now()->format('Y-m-d'),
           'template' => 'payment_schedule',
           'parameters' => [
               'invoice_id' => $invoice->hashed_id,
               'auto_bill' => true,
               'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 40,
                    'is_amount' => true,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 60.00,
                    'is_amount' => true,
                ]
               ],
           ],
       ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $scheduler = Scheduler::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertNotNull($scheduler);

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        $this->assertEquals(40, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), $invoice->partial_due_date->format('Y-m-d'));

        $scheduler = $scheduler->fresh();

        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));

        $this->travelTo(now()->addDays(30));

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        $this->assertEquals(100, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), $invoice->partial_due_date->format('Y-m-d'));

        $this->travelBack();
    }


    public function testPaymentScheduleCalculationsIsAmount()
    {
        $settings = $this->company->settings;
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';
        $this->company->settings = $settings;
        $this->company->save();

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 300.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);


        $data = [
           'name' => 'A test payment schedule scheduler',
           'frequency_id' => 0,
           'next_run' => now()->format('Y-m-d'),
           'template' => 'payment_schedule',
           'parameters' => [
               'invoice_id' => $invoice->hashed_id,
               'auto_bill' => false,
               'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 40,
                    'is_amount' => true,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 60.00,
                    'is_amount' => true,
                ]
               ],
           ],
       ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $scheduler = Scheduler::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertNotNull($scheduler);

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        $this->assertEquals(40, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), $invoice->partial_due_date->format('Y-m-d'));

        $scheduler = $scheduler->fresh();

        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));
    }

    public function testPaymentScheduleCalculationsIsPercentage()
    {
        $settings = $this->company->settings;
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';
        $this->company->settings = $settings;
        $this->company->save();

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 300.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);


        $data = [
           'name' => 'A test payment schedule scheduler',
           'frequency_id' => 0,
           'next_run' => now()->format('Y-m-d'),
           'template' => 'payment_schedule',
           'parameters' => [
               'invoice_id' => $invoice->hashed_id,
               'auto_bill' => false,
               'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 40,
                    'is_amount' => false,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 60.00,
                    'is_amount' => false,
                ]
               ],
           ],
       ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $scheduler = Scheduler::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertNotNull($scheduler);

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        $this->assertEquals(120, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), $invoice->partial_due_date->format('Y-m-d'));
    }

    public function testDuplicateInvoicePaymentSchedule()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'amount' => 300.00,
            'balance' => 300.00,
        ]);

        $invoice->service()->markSent()->save();

        $data = [
           'name' => 'A test payment schedule scheduler',
           'frequency_id' => 0,
           'next_run' => now()->format('Y-m-d'),
           'template' => 'payment_schedule',
           'parameters' => [
               'invoice_id' => $invoice->hashed_id,
               'auto_bill' => true,
               'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 40,
                    'is_amount' => false,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 60.00,
                    'is_amount' => false,
                ]
               ],
           ],
       ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);

    }

    public function testPaymentScheduleWithPercentageBasedScheduleAndFailingValidation()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'amount' => 300.00,
            'balance' => 300.00,
        ]);

        $invoice->service()->markSent()->save();

        $data = [
            'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 40,
                    'is_amount' => false,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 50.00,
                    'is_amount' => false,
                ]
            ],
            'auto_bill' => true,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule?show_schedule=true', $data);

        $response->assertStatus(422);

    }

    public function testPaymentScheduleWithPercentageBasedSchedule()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'amount' => 300.00,
            'balance' => 300.00,
        ]);

        $invoice->service()->markSent()->save();

        $data = [
            'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 40,
                    'is_amount' => false,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 60.00,
                    'is_amount' => false,
                ]
            ],
            'auto_bill' => true,
            'next_run' => now()->addDay()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule?show_schedule=true', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, count($arr['data']['schedule']));
        $this->assertEquals(now()->format($this->company->date_format()), $arr['data']['schedule'][0]['date']);
        $this->assertEquals(now()->addDays(30)->format($this->company->date_format()), $arr['data']['schedule'][1]['date']);
    }


    public function testPaymentScheduleRequestValidation()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'amount' => 300.00,
            'balance' => 300.00,
        ]);

        $invoice->service()->markSent()->save();

        $data = [
            'schedule' => [
                [
                    'id' => 1,
                    'date' => now()->format('Y-m-d'),
                    'amount' => 100.00,
                    'is_amount' => true,
                ],
                [
                    'id' => 2,
                    'date' => now()->addDays(30)->format('Y-m-d'),
                    'amount' => 200.00,
                    'is_amount' => true,
                ]
            ],
            'auto_bill' => true,
            'next_run' => now()->addDay()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule?show_schedule=true', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, count($arr['data']['schedule']));
        $this->assertEquals(now()->format($this->company->date_format()), $arr['data']['schedule'][0]['date']);
        $this->assertEquals(now()->addDays(30)->format($this->company->date_format()), $arr['data']['schedule'][1]['date']);
    }

    public function testPaymentScheduleRequestWithFrequency()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'amount' => 300.00,
            'balance' => 300.00,
        ]);

        $invoice->service()->markSent()->save();


        $data = [
            'frequency_id' => 5, // Monthly
            'remaining_cycles' => 3,
            'auto_bill' => false,
            'next_run' => now()->addDays(30)->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule?show_schedule=true', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $date = Carbon::parse($invoice->due_date);

        $this->assertEquals(3, count($arr['data']['schedule']));
        $this->assertEquals($date->startOfDay()->format($this->company->date_format()), $arr['data']['schedule'][0]['date']);
        $this->assertEquals($date->addMonthNoOverflow()->format($this->company->date_format()), $arr['data']['schedule'][1]['date']);
        $this->assertEquals($date->addMonthNoOverflow()->format($this->company->date_format()), $arr['data']['schedule'][2]['date']);
    }

    public function testPaymentSchedule()
    {
        $data = [
            [
            'date' => now()->format('Y-m-d'),
            'amount' => 100,
            'percentage' => 100,
            ],
            [
            'date' => now()->addDays(1)->format('Y-m-d'),
            'amount' => 100,
            'percentage' => 100,
            ],
            [
            'date' => now()->addDays(2)->format('Y-m-d'),
            'amount' => 100,
            'percentage' => 100,
            ],
        ];

        $offset = -3600;

        $next_schedule = collect($data)->first(function ($item) use ($offset) {
            return now()->startOfDay()->eq(Carbon::parse($item['date'])->subSeconds($offset)->startOfDay());
        });

        $this->assertNotNull($next_schedule);

        $this->assertEquals(Carbon::parse($next_schedule['date'])->format($this->company->date_format()), now()->format($this->company->date_format()));

        $this->travelTo(now()->addDays(1));

        $next_schedule = collect($data)->first(function ($item) use ($offset) {
            return now()->startOfDay()->eq(Carbon::parse($item['date'])->subSeconds($offset)->startOfDay());
        });

        $this->assertNotNull($next_schedule);

        $this->assertEquals(Carbon::parse($next_schedule['date'])->format($this->company->date_format()), now()->format($this->company->date_format()));

    }

    public function testInvoiceOutstandingTasks()
    {

        $start = now()->subMonth()->addDays(1)->timestamp;
        $end = now()->subMonth()->addDays(5)->timestamp;

        Task::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'description' => 'Test task',
            'time_log' => '[['.$start.','.$end.',null,false]]',
            'rate' => 100,
        ]);

        $data = [
            'name' => 'A test invoice outstanding tasks scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'invoice_outstanding_tasks',
            'parameters' => [
                'clients' => [],
                'include_project_tasks' => true,
                'auto_send' => true,
                'date_range' => 'last_month',
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $id = $this->decodePrimaryKey($arr['data']['id']);
        $scheduler = Scheduler::find($id);
        $user = $scheduler->user;
        $user->email = "{rand(5,555555}@gmail.com";
        $user->save();

        $this->assertNotNull($scheduler);

        $export = (new InvoiceOutstandingTasksService($scheduler))->run();


    }


    public function testReportValidationRulesForStartAndEndDate()
    {
        $data = [
            'name' => 'A test product sales scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_statement',
            'parameters' => [
                'date_range' => 'custom',
                'clients' => [],
                'report_keys' => [],
                'client_id' => $this->client->hashed_id,
            ],

        ];

        $response = false;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);

    }

    public function testReportValidationRules()
    {
        $data = [
            'name' => 'A test product sales scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_report',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'clients' => [],
                'report_keys' => [],
                'client_id' => $this->client->hashed_id,
                'report_name' => '',
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);

    }


    public function testProductSalesReportGenerationOneClientSeparateParam()
    {
        $data = [
            'name' => 'A test product sales scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->startOfDay()->format('Y-m-d'),
            'template' => 'email_report',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'clients' => [],
                'report_keys' => [],
                'client_id' => $this->client->hashed_id,
                'report_name' => 'product_sales',
                'user_id' => $this->user->id,
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $id = $this->decodePrimaryKey($arr['data']['id']);
        $scheduler = Scheduler::find($id);
        $user = $scheduler->user;
        $user->email = "{rand(5,555555}@gmail.com";
        $user->save();

        $this->assertNotNull($scheduler);

        $export = (new EmailReport($scheduler))->run();


        // nlog($scheduler->fresh()->toArray());
        $this->assertEquals(now()->startOfDay()->addMonthNoOverflow()->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));

    }

    public function testProductSalesReportGenerationOneClient()
    {
        $data = [
            'name' => 'A test product sales scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_report',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'clients' => [$this->client->hashed_id],
                'report_keys' => [],
                'client_id' => null,
                'report_name' => 'product_sales',
                'user_id' => $this->user->id,
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);


        $arr = $response->json();

        $id = $this->decodePrimaryKey($arr['data']['id']);
        $scheduler = Scheduler::find($id);
        $user = $scheduler->user;
        $user->email = "{rand(5,555555}@gmail.com";
        $user->save();

        $this->assertNotNull($scheduler);

        $export = (new EmailReport($scheduler))->run();

        $this->assertEquals(now()->addMonthNoOverflow()->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));

    }

    public function testProductSalesReportGeneration()
    {
        $data = [
            'name' => 'A test product sales scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_report',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'clients' => [],
                'report_keys' => [],
                'client_id' => null,
                'report_name' => 'product_sales',
                'user_id' => $this->user->id,
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);


        $arr = $response->json();

        $id = $this->decodePrimaryKey($arr['data']['id']);
        $scheduler = Scheduler::query()->find($id);

        $this->assertNotNull($scheduler);

        $export = (new EmailReport($scheduler))->run();

        $this->assertEquals(now()->addMonthNoOverflow()->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));

    }

    public function testProductSalesReportStore()
    {
        $data = [
            'name' => 'A test product sales scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'email_report',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'clients' => [],
                'report_name' => 'product_sales',
                'user_id' => $this->user->id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
    }


    public function testSchedulerGet3()
    {

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        $scheduler->name = "hello";
        $scheduler->save();

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        $scheduler->name = "goodbye";
        $scheduler->save();


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_schedulers?filter=hello');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('hello', $arr['data'][0]['name']);
        $this->assertCount(1, $arr['data']);

    }

    public function testSchedulerGet2()
    {

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_schedulers/'.$this->encodePrimaryKey($scheduler->id));

        $response->assertStatus(200);
    }


    public function testCustomDateRanges()
    {
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'user_id' => $this->user->id,
                'date_range' => EmailStatement::CUSTOM_RANGE,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addDays(4)->format('Y-m-d')
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
    }

    public function testCustomDateRangesFails()
    {
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'user_id' => $this->user->id,
                'date_range' => EmailStatement::CUSTOM_RANGE,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->subDays(4)->format('Y-m-d')
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);


        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::CUSTOM_RANGE,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
                'start_date' => now()->format('Y-m-d'),
                'end_date' => null,
                'user_id' => $this->user->id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::CUSTOM_RANGE,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
                'start_date' => null,
                'end_date' => now()->format('Y-m-d'),
                'user_id' => $this->user->id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);



        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::CUSTOM_RANGE,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
                'start_date' => '',
                'end_date' => '',
                'user_id' => $this->user->id,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);

    }

    public function testClientCountResolution()
    {
        $c = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 100000),
            'name' => 'A fancy client'
        ]);

        $c2 = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 100000),
            'name' => 'A fancy client'
        ]);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    $c2->hashed_id,
                    $c->hashed_id
                ],
            ],
        ];

        $response = false;
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $data = $response->json();

        $scheduler = Scheduler::find($this->decodePrimaryKey($data['data']['id']));

        $this->assertInstanceOf(Scheduler::class, $scheduler);

        $this->assertCount(2, $scheduler->parameters['clients']);

        $q = Client::query()
              ->where('company_id', $scheduler->company_id)
              ->whereIn('id', $this->transformKeys($scheduler->parameters['clients']))
              ->cursor();

        $this->assertCount(2, $q);
    }

    public function testClientsValidationInScheduledTask()
    {
        $c = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 10000000),
            'name' => 'A fancy client'
        ]);

        $c2 = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => rand(1000, 10000000),
            'name' => 'A fancy client'
        ]);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    $c2->hashed_id,
                    $c->hashed_id
                ],
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $data = [
            'name' => 'A single Client',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->addDay()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    $c2->hashed_id,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);


        $data = [
            'name' => 'An invalid Client',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [
                    'xx33434',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);
    }


    public function testCalculateNextRun()
    {
        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $scheduler->fill($data);
        $scheduler->save();
        $scheduler->calculateNextRun();

        $scheduler->fresh();
        $offset = $this->company->timezone_offset();

        $this->assertEquals(now()->startOfDay()->addMonthNoOverflow()->addSeconds($offset)->format('Y-m-d'), $scheduler->next_run->format('Y-m-d'));
    }

    public function testCalculateStartAndEndDates()
    {
        $this->travelTo(Carbon::parse('2023-01-01'));

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => "2023-01-01",
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $scheduler->fill($data);
        $scheduler->save();
        $scheduler->calculateNextRun();

        $service_object = new EmailStatementService($scheduler);

        $reflectionMethod = new \ReflectionMethod(EmailStatementService::class, 'calculateStartAndEndDates');
        $reflectionMethod->setAccessible(true);
        $method = $reflectionMethod->invoke(new EmailStatementService($scheduler), $this->client);

        $this->assertIsArray($method);

        $this->assertEquals(EmailStatement::LAST_MONTH, $scheduler->parameters['date_range']);

        $this->assertEqualsCanonicalizing(['2022-12-01','2022-12-31'], $method);
    }

    public function testCalculateStatementProperties()
    {
        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);

        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $scheduler->fill($data);
        $scheduler->save();

        $service_object = new EmailStatementService($scheduler);

        $reflectionMethod = new \ReflectionMethod(EmailStatementService::class, 'calculateStatementProperties');
        $reflectionMethod->setAccessible(true);
        $method = $reflectionMethod->invoke(new EmailStatementService($scheduler), $this->client);

        $this->assertIsArray($method);

        $this->assertEquals('paid', $method['status']);
    }

    public function testGetThisMonthRange()
    {
        $this->travelTo(Carbon::parse('2023-01-14'));

        $this->assertEqualsCanonicalizing(['2023-01-01','2023-01-31'], $this->getDateRange(EmailStatement::THIS_MONTH));
        $this->assertEqualsCanonicalizing(['2023-01-01','2023-03-31'], $this->getDateRange(EmailStatement::THIS_QUARTER));
        $this->assertEqualsCanonicalizing(['2023-01-01','2023-12-31'], $this->getDateRange(EmailStatement::THIS_YEAR));

        $this->assertEqualsCanonicalizing(['2022-12-01','2022-12-31'], $this->getDateRange(EmailStatement::LAST_MONTH));
        $this->assertEqualsCanonicalizing(['2022-10-01','2022-12-31'], $this->getDateRange(EmailStatement::LAST_QUARTER));
        $this->assertEqualsCanonicalizing(['2022-01-01','2022-12-31'], $this->getDateRange(EmailStatement::LAST_YEAR));

        $this->travelBack();
    }

    private function getDateRange($range)
    {
        return match ($range) {
            EmailStatement::LAST7 => [now()->startOfDay()->subDays(7)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST30 => [now()->startOfDay()->subDays(30)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST365 => [now()->startOfDay()->subDays(365)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::THIS_MONTH => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::LAST_MONTH => [now()->startOfDay()->subMonthNoOverflow()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->subMonthNoOverflow()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::THIS_QUARTER => [now()->startOfDay()->firstOfQuarter()->format('Y-m-d'), now()->startOfDay()->lastOfQuarter()->format('Y-m-d')],
            EmailStatement::LAST_QUARTER => [now()->startOfDay()->subQuarterNoOverflow()->firstOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuarterNoOverflow()->lastOfQuarter()->format('Y-m-d')],
            EmailStatement::THIS_YEAR => [now()->startOfDay()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->lastOfYear()->format('Y-m-d')],
            EmailStatement::LAST_YEAR => [now()->startOfDay()->subYearNoOverflow()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->subYearNoOverflow()->lastOfYear()->format('Y-m-d')],
            EmailStatement::CUSTOM_RANGE => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']],
            default => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
        };
    }

    public function testClientStatementGeneration()
    {
        $data = [
            'name' => 'A test statement scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [
                'date_range' => EmailStatement::LAST_MONTH,
                'show_payments_table' => true,
                'show_aging_table' => true,
                'status' => 'paid',
                'clients' => [],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
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
            'template' => 'client_statement',
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
            'template' => 'client_statement',
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
