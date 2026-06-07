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
use App\Models\Company;
use App\Models\Invoice;
use App\DataMapper\CompanySettings;
use App\Models\Project;
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
use App\Http\Requests\TaskScheduler\PaymentScheduleRequest;
use App\Utils\Traits\MakesDates;
use PHPUnit\Framework\Attributes\DataProvider;

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

    private function makeIoTBillableTask(
        Client $client,
        string $calculatedStartDate,
        ?int $invoiceId = null,
        ?int $projectId = null,
        bool $billableInterval = true,
        bool $running = false,
    ): Task {
        $dayStart = Carbon::parse($calculatedStartDate.' 12:00:00', 'UTC')->timestamp;
        $dayEnd = $running ? 0 : ($dayStart + 7200);
        $billFlag = $billableInterval ? 'true' : 'false';
        $timeLog = '[['.$dayStart.','.$dayEnd.',null,'.$billFlag.']]';

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'description' => 'IoT test '.$calculatedStartDate,
            'time_log' => $timeLog,
            'rate' => 100,
            'invoice_id' => $invoiceId,
            'project_id' => $projectId,
        ]);
        $task->forceFill(['calculated_start_date' => $calculatedStartDate])->save();

        return $task->fresh();
    }

    private function createInvoiceOutstandingTasksSchedulerViaApi(array $parameterOverrides = [], array $payloadOverrides = []): Scheduler
    {
        $parameters = array_merge([
            'clients' => [],
            'include_project_tasks' => false,
            'auto_send' => false,
            'date_range' => EmailStatement::THIS_MONTH,
        ], $parameterOverrides);

        $payload = array_merge([
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'invoice_outstanding_tasks',
            'parameters' => $parameters,
        ], $payloadOverrides);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $payload);

        $response->assertStatus(200);

        return Scheduler::with('company')->findOrFail($this->decodePrimaryKey($response->json('data.id')));
    }

    /**
     * @return list<string>
     */
    private function collectTaskIdsFromInvoiceLineItems(?Invoice $invoice): array
    {
        if ($invoice === null) {
            return [];
        }

        return collect((array) $invoice->line_items)
            ->map(fn ($item) => (array) $item)
            ->pluck('task_id')
            ->filter()
            ->values()
            ->all();
    }

    private function clientPrimaryInvoiceCount(): int
    {
        return Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $this->client->id)
            ->count();
    }

    private function clientInvoiceCount(Client $client): int
    {
        return Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $client->id)
            ->count();
    }

    private function assertSchedulerNextRunMonthly(Scheduler $scheduler): void
    {
        $scheduler->refresh();
        $expectedNextClient = now()->startOfDay()->addMonthNoOverflow();
        $this->assertTrue(
            $scheduler->next_run_client->equalTo($expectedNextClient),
            'next_run_client should be start of day plus one month from the frozen clock.'
        );
        $offset = $scheduler->company->timezone_offset();
        $this->assertTrue(
            $scheduler->next_run->equalTo($expectedNextClient->copy()->addSeconds($offset)),
            'next_run should match next_run_client adjusted by company timezone_offset().'
        );
    }

    /**
     * @param  array<int, string>  $expectedTwoDates
     * @param  array<int, string>  $actualTwoDates
     */
    private function assertSameTwoDateStringsOrderIndependent(array $expectedTwoDates, array $actualTwoDates): void
    {
        $this->assertCount(2, $expectedTwoDates);
        $this->assertCount(2, $actualTwoDates);
        $e = array_values($expectedTwoDates);
        $a = array_values($actualTwoDates);
        sort($e);
        sort($a);
        $this->assertSame($e, $a);
    }

    private function assertTaskHashedIdInList(string $hashedId, array $taskHashedIds): void
    {
        $this->assertTrue(
            in_array($hashedId, $taskHashedIds, true),
            sprintf('Expected task id %s in %s', $hashedId, json_encode($taskHashedIds))
        );
    }

    private function assertTaskHashedIdNotInList(string $hashedId, array $taskHashedIds): void
    {
        $this->assertFalse(
            in_array($hashedId, $taskHashedIds, true),
            sprintf('Did not expect task id %s in %s', $hashedId, json_encode($taskHashedIds))
        );
    }

    public static function invoiceOutstandingTasksDateRangeProvider(): iterable
    {
        $june = '2026-06-15 12:00:00';

        yield 'last7_days' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::LAST7,
            'dateInRange' => '2026-06-10',
            'dateOutOfRange' => '2026-06-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'last30_days' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::LAST30,
            'dateInRange' => '2026-05-20',
            'dateOutOfRange' => '2026-04-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'last365_days' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::LAST365,
            'dateInRange' => '2025-08-01',
            'dateOutOfRange' => '2024-01-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'this_month' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::THIS_MONTH,
            'dateInRange' => '2026-06-10',
            'dateOutOfRange' => '2026-05-15',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'last_month' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::LAST_MONTH,
            'dateInRange' => '2026-05-10',
            'dateOutOfRange' => '2026-06-02',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'this_quarter' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::THIS_QUARTER,
            'dateInRange' => '2026-05-01',
            'dateOutOfRange' => '2026-03-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'last_quarter' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::LAST_QUARTER,
            'dateInRange' => '2026-03-15',
            'dateOutOfRange' => '2026-05-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'this_year' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::THIS_YEAR,
            'dateInRange' => '2026-03-01',
            'dateOutOfRange' => '2025-12-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'last_year' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::LAST_YEAR,
            'dateInRange' => '2025-08-01',
            'dateOutOfRange' => '2026-02-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'all_time' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::ALL_TIME,
            'dateInRange' => '2024-06-01',
            'dateOutOfRange' => '2027-01-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'date_range_all_maps_to_default_this_month' => [
            'frozenAt' => $june,
            'dateRange' => 'all',
            'dateInRange' => '2026-06-12',
            'dateOutOfRange' => '2026-05-01',
            'customStart' => null,
            'customEnd' => null,
        ];
        yield 'custom' => [
            'frozenAt' => $june,
            'dateRange' => EmailStatement::CUSTOM_RANGE,
            'dateInRange' => '2026-05-10',
            'dateOutOfRange' => '2026-05-25',
            'customStart' => '2026-05-05',
            'customEnd' => '2026-05-20',
        ];
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

        // First instalment is seeded at creation; the scheduler advances to the second instalment.
        $invoice = $invoice->fresh();

        $this->assertEquals(30, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));

        $scheduler = $scheduler->fresh();

        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($scheduler->next_run)->format('Y-m-d'));

        $this->travelTo(now()->addDays(30));

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        // Last instalment: partial is unset and the entire remaining balance is due today.
        $this->assertNull($invoice->partial);
        $this->assertNull($invoice->partial_due_date);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));

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
            'amount' => 100.00,
            'balance' => 100.00,
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

        // First instalment is seeded at creation; the scheduler advances to the second instalment.
        $invoice = $invoice->fresh();

        $this->assertEquals(40, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));

        $scheduler = $scheduler->fresh();

        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($scheduler->next_run)->format('Y-m-d'));

        $this->travelTo(now()->addDays(30));

        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();

        // Last instalment: partial is unset and the entire remaining balance is due today.
        $this->assertNull($invoice->partial);
        $this->assertNull($invoice->partial_due_date);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));

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
            'amount' => 100.00,
            'balance' => 100.00,
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

        // First instalment is seeded at creation; the scheduler advances to the second instalment.
        $invoice = $invoice->fresh();

        $this->assertEquals(40, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));

        $scheduler = $scheduler->fresh();

        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($scheduler->next_run)->format('Y-m-d'));
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

        // First instalment (40% of 300) is seeded at creation.
        $invoice = $invoice->fresh();

        $this->assertEquals(120, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));
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

    public function testPaymentScheduleSeededViaTaskSchedulerEndpoint()
    {
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
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        // Creating directly via the task scheduler endpoint seeds the invoice identically.
        $invoice = $invoice->fresh();
        $this->assertEquals(100, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));
        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));

        $scheduler = Scheduler::find($this->decodePrimaryKey($response->json()['data']['id']));
        $this->assertEquals(now()->addDays(15)->format('Y-m-d'), Carbon::parse($scheduler->next_run_client)->format('Y-m-d'));
    }

    public function testPaymentScheduleViaTaskSchedulerRejectsMismatchedTotal()
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
                'auto_bill' => false,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 40, 'is_amount' => true],
                    ['id' => 2, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 60, 'is_amount' => true],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        // 40 + 60 != invoice amount of 300.
        $response->assertStatus(422);
    }

    public function testPaymentScheduleSeedsFirstInstalmentOnCreation()
    {
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
        ]);

        $invoice->service()->markSent()->save();

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();

        // First instalment is hard set as the partial; due_date is the last instalment.
        $this->assertEquals(100, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));
        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));

        $scheduler = Scheduler::where('company_id', $invoice->company_id)
            ->where('template', 'payment_schedule')
            ->where('parameters->invoice_id', $invoice->hashed_id)
            ->first();

        $this->assertNotNull($scheduler);

        // Scheduler advances to the second instalment so run() never re-applies the first.
        $this->assertEquals(now()->addDays(15)->format('Y-m-d'), Carbon::parse($scheduler->next_run_client)->format('Y-m-d'));
    }

    public function testPaymentScheduleSeedsPartialOnDraftInvoice()
    {
        // A draft invoice has a computed amount but its balance is not yet populated (0),
        // which is the state on the invoice edit tab. The partial must still be seeded.
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
        ]);

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();

        // The zero balance of a draft must NOT clamp the partial to 0.
        $this->assertEquals(100, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));
        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));

        // Marking the draft sent must populate the balance without disturbing the seeded schedule.
        $invoice->service()->markSent()->save();

        $invoice = $invoice->fresh();

        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertEquals(300, $invoice->balance);
        $this->assertEquals(100, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));
        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));
    }

    public function testPaymentScheduleSeedsPercentagePartialOnDraftInvoice()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
        ]);

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 40, 'is_amount' => false],
                ['id' => 2, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 60, 'is_amount' => false],
            ],
            'auto_bill' => false,
            'next_run' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();

        // 40% of the 300 amount, not clamped to the draft's zero balance.
        $this->assertEquals(120, $invoice->partial);
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($invoice->partial_due_date)->format('Y-m-d'));
    }

    public function testPaymentScheduleSingleInstalmentSeedsOnDraftInvoice()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
        ]);

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->addDays(10)->format('Y-m-d'), 'amount' => 300, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->addDays(10)->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();

        // Single instalment = full balance due on that date; no partial regardless of draft state.
        $this->assertNull($invoice->partial);
        $this->assertNull($invoice->partial_due_date);
        $this->assertEquals(now()->addDays(10)->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));
    }

    public function testPaymentScheduleFullLifecycleDraftToSentToCompletion()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
        ]);

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data);

        $response->assertStatus(200);

        $scheduler = Scheduler::where('company_id', $invoice->company_id)
            ->where('template', 'payment_schedule')
            ->where('parameters->invoice_id', $invoice->hashed_id)
            ->first();

        // Seeded on the draft.
        $this->assertEquals(100, $invoice->fresh()->partial);

        // Promote to sent.
        $invoice->service()->markSent()->save();
        $this->assertEquals(300, $invoice->fresh()->balance);

        // Second interval, first left unpaid -> accumulates.
        $this->travelTo(now()->addDays(15));
        $scheduler->service()->runTask();
        $this->assertEquals(200, $invoice->fresh()->partial);

        // Final interval -> partial cleared, full balance due, scheduler cleaned up.
        // (travelTo is absolute; we are already at +15, so +15 more lands on the +30 instalment.)
        $this->travelTo(now()->addDays(15));
        $scheduler->service()->runTask();
        $this->travelBack();

        $invoice = $invoice->fresh();
        $this->assertNull($invoice->partial);
        $this->assertNull($invoice->partial_due_date);

        $remaining = Scheduler::where('company_id', $invoice->company_id)
            ->where('template', 'payment_schedule')
            ->where('parameters->invoice_id', $invoice->hashed_id)
            ->first();
        $this->assertNull($remaining);
    }

    public function testPaymentSchedulePercentageRunToCompletionReconcilesToBalance()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 100.00,
            'balance' => 100.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        // Percentages that don't divide cleanly - the final instalment must reconcile.
        $data = [
            'name' => 'Thirds',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $invoice->hashed_id,
                'auto_bill' => false,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 33.34, 'is_amount' => false],
                    ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 33.33, 'is_amount' => false],
                    ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 33.33, 'is_amount' => false],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        $scheduler = Scheduler::find($this->decodePrimaryKey($response->json()['data']['id']));

        // Seeded first instalment: 33.34% of 100.
        $this->assertEquals(33.34, $invoice->fresh()->partial);

        $this->travelTo(now()->addDays(30));
        $scheduler->service()->runTask();
        $this->travelBack();

        // Final instalment clears partial; the outstanding balance (full 100) is what's due.
        $invoice = $invoice->fresh();
        $this->assertNull($invoice->partial);
        $this->assertEquals(100, $invoice->balance);
    }

    public function testDeletePaymentScheduleResetsPartialOnDraftInvoice()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
            'amount' => 300.00,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
        ]);

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
                ['id' => 2, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->format('Y-m-d'),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data)
            ->assertStatus(200);

        $this->assertEquals(150, $invoice->fresh()->partial);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->deleteJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule')
            ->assertStatus(200);

        $invoice = $invoice->fresh();
        $this->assertEquals(0, $invoice->partial);
        $this->assertNull($invoice->partial_due_date);
    }

    public function testPaymentSchedulePaymentBetweenIntervalsThenAccumulates()
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
            'schedule' => [
                ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->format('Y-m-d'),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data)
            ->assertStatus(200);

        $scheduler = Scheduler::where('company_id', $invoice->company_id)
            ->where('template', 'payment_schedule')
            ->where('parameters->invoice_id', $invoice->hashed_id)
            ->first();

        // First instalment seeded as the partial.
        $this->assertEquals(100, $invoice->fresh()->partial);

        // The client pays the first instalment - the partial drains to 0 and balance drops.
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 100,
            'client_id' => $this->client->hashed_id,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                ['invoice_id' => $invoice->hashed_id, 'amount' => 100],
            ],
        ])->assertStatus(200);

        $invoice = $invoice->fresh();
        $this->assertEquals(0, $invoice->partial);
        $this->assertEquals(200, $invoice->balance);

        // The second interval runs - only the new instalment is now due (no stale accumulation).
        $this->travelTo(now()->addDays(15));
        $scheduler->service()->runTask();
        $this->travelBack();

        $invoice = $invoice->fresh();
        $this->assertEquals(100, $invoice->partial);
        $this->assertEquals(200, $invoice->balance);
    }

    public function testPaymentScheduleSingleInstalmentSeedsAsLast()
    {
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
        ]);

        $invoice->service()->markSent()->save();

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->addDays(10)->format('Y-m-d'), 'amount' => 300, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->addDays(10)->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();

        // A single instalment is the last instalment: no partial, full balance due on that date.
        $this->assertNull($invoice->partial);
        $this->assertNull($invoice->partial_due_date);
        $this->assertEquals(now()->addDays(10)->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));

        $scheduler = Scheduler::where('company_id', $invoice->company_id)
            ->where('template', 'payment_schedule')
            ->where('parameters->invoice_id', $invoice->hashed_id)
            ->first();

        // Scheduler is retained (it still blocks a duplicate schedule) and runs on the instalment date.
        $this->assertNotNull($scheduler);
        $this->assertEquals(now()->addDays(10)->format('Y-m-d'), Carbon::parse($scheduler->next_run_client)->format('Y-m-d'));

        // On the instalment date the schedule finalises and cleans itself up.
        $this->travelTo(now()->addDays(10));
        $scheduler->service()->runTask();
        $this->travelBack();

        $scheduler = Scheduler::where('company_id', $invoice->company_id)
            ->where('template', 'payment_schedule')
            ->where('parameters->invoice_id', $invoice->hashed_id)
            ->first();

        $this->assertNull($scheduler);
    }

    public function testDeletePaymentScheduleResetsPartial()
    {
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
        ]);

        $invoice->service()->markSent()->save();

        $data = [
            'schedule' => [
                ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
                ['id' => 2, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
            ],
            'auto_bill' => false,
            'next_run' => now()->format('Y-m-d'),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule', $data)
            ->assertStatus(200);

        $invoice = $invoice->fresh();
        $this->assertEquals(150, $invoice->partial);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->deleteJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule')
            ->assertStatus(200);

        $invoice = $invoice->fresh();

        $this->assertEquals(0, $invoice->partial);
        $this->assertNull($invoice->partial_due_date);

        $scheduler = Scheduler::where('company_id', $invoice->company_id)
            ->where('template', 'payment_schedule')
            ->where('parameters->invoice_id', $invoice->hashed_id)
            ->first();

        $this->assertNull($scheduler);
    }

    public function testUpdatingPaymentScheduleCannotChangeInvoiceId()
    {
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

        $invoice->service()->markSent()->save();

        // A second invoice we will (unsuccessfully) attempt to re-point the schedule at.
        $other_invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'amount' => 500.00,
            'balance' => 500.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $other_invoice->service()->markSent()->save();

        $create = [
            'name' => 'Payment schedule',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $invoice->hashed_id,
                'auto_bill' => false,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $create);

        $response->assertStatus(200);

        $scheduler = Scheduler::find($this->decodePrimaryKey($response->json()['data']['id']));

        // The schedule is created bound to the original invoice.
        $this->assertEquals($invoice->hashed_id, $scheduler->parameters['invoice_id']);

        // Attempt to re-point the schedule at a different invoice while editing the schedule.
        $update = [
            'name' => 'Payment schedule updated',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $other_invoice->hashed_id,
                'auto_bill' => false,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
                    ['id' => 2, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/task_schedulers/'.$scheduler->hashed_id, $update);

        $response->assertStatus(200);

        $scheduler = $scheduler->fresh();

        // invoice_id is immutable after creation: the supplied other_invoice id is ignored
        // and the original is re-asserted. The schedule is immutable too - the [150,150]
        // payload is discarded and the original three instalments are retained.
        $this->assertArrayHasKey('invoice_id', $scheduler->parameters);
        $this->assertEquals($invoice->hashed_id, $scheduler->parameters['invoice_id']);
        $this->assertNotEquals($other_invoice->hashed_id, $scheduler->parameters['invoice_id']);
        $this->assertCount(3, $scheduler->parameters['schedule']);
        $this->assertEquals([100, 100, 100], array_map(fn ($s) => $s['amount'], $scheduler->parameters['schedule']));
    }

    public function testUpdatingPaymentScheduleOnlyMutatesNameAndAutoBill()
    {
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

        $invoice->service()->markSent()->save();

        $create = [
            'name' => 'Original name',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $invoice->hashed_id,
                'auto_bill' => false,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $create);

        $response->assertStatus(200);

        $scheduler = Scheduler::find($this->decodePrimaryKey($response->json()['data']['id']));

        // Edit name + auto_bill, and (futilely) attempt to rewind next_run and rewrite the schedule.
        $update = [
            'name' => 'Renamed schedule',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $invoice->hashed_id,
                'auto_bill' => true,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 300, 'is_amount' => true],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/task_schedulers/'.$scheduler->hashed_id, $update);

        $response->assertStatus(200);

        $scheduler = $scheduler->fresh();
        $invoice = $invoice->fresh();

        // Mutable: name + auto_bill changed.
        $this->assertEquals('Renamed schedule', $scheduler->name);
        $this->assertTrue((bool) $scheduler->parameters['auto_bill']);

        // Immutable: schedule and run cursor are unchanged, invoice runtime state untouched.
        $this->assertCount(3, $scheduler->parameters['schedule']);
        $this->assertEquals(now()->addDays(15)->format('Y-m-d'), Carbon::parse($scheduler->next_run_client)->format('Y-m-d'));
        $this->assertEquals(100, $invoice->partial);
        $this->assertEquals(now()->addDays(30)->format('Y-m-d'), Carbon::parse($invoice->due_date)->format('Y-m-d'));
    }

    public function testUpdatingPaymentScheduleDoesNotResetAccumulatedPartial()
    {
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

        $invoice->service()->markSent()->save();

        $create = [
            'name' => 'Accumulating schedule',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $invoice->hashed_id,
                'auto_bill' => false,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 2, 'date' => now()->addDays(15)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                    ['id' => 3, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 100, 'is_amount' => true],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $create);

        $response->assertStatus(200);

        $scheduler = Scheduler::find($this->decodePrimaryKey($response->json()['data']['id']));

        // First instalment seeded (100). Run the second interval with the first left unpaid,
        // so the unpaid first accumulates: partial += second instalment => 200.
        $this->travelTo(now()->addDays(15));
        $scheduler->service()->runTask();

        $invoice = $invoice->fresh();
        $this->assertEquals(200, $invoice->partial);

        // Toggle auto_bill - the accumulated partial must NOT be recomputed back to 100.
        $update = [
            'name' => 'Accumulating schedule',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $invoice->hashed_id,
                'auto_bill' => true,
                'schedule' => $create['parameters']['schedule'],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/task_schedulers/'.$scheduler->hashed_id, $update);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();

        // Accumulated runtime state is preserved across the update.
        $this->assertEquals(200, $invoice->partial);

        $this->travelBack();
    }

    public function testCannotCreatePaymentScheduleForAnotherCompanysInvoice()
    {
        // An invoice belonging to a different company within the same account.
        $other_company = Company::factory()->create([
            'account_id' => $this->company->account_id,
            'settings' => CompanySettings::defaults(),
        ]);

        $other_client = Client::factory()->create([
            'company_id' => $other_company->id,
            'user_id' => $this->user->id,
        ]);

        $foreign_invoice = Invoice::factory()->create([
            'company_id' => $other_company->id,
            'user_id' => $this->user->id,
            'client_id' => $other_client->id,
            'amount' => 300.00,
            'balance' => 300.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $data = [
            'name' => 'Payment schedule',
            'frequency_id' => 0,
            'next_run' => now()->format('Y-m-d'),
            'template' => 'payment_schedule',
            'parameters' => [
                'invoice_id' => $foreign_invoice->hashed_id,
                'auto_bill' => false,
                'schedule' => [
                    ['id' => 1, 'date' => now()->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
                    ['id' => 2, 'date' => now()->addDays(30)->format('Y-m-d'), 'amount' => 150, 'is_amount' => true],
                ],
            ],
        ];

        // Posting with this company's token must not be able to schedule another company's invoice.
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);
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
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $inRangeTask = $this->makeIoTBillableTask($this->client, '2026-05-10', null, null, true, false);
        $outOfRangeTask = $this->makeIoTBillableTask($this->client, '2026-06-02');

        $existingInvoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => '2026-05-01',
            'due_date' => '2026-06-01',
        ]);
        $inRangeInvoicedTask = $this->makeIoTBillableTask($this->client, '2026-05-20', $existingInvoice->id);

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi([
            'include_project_tasks' => true,
            'date_range' => EmailStatement::LAST_MONTH,
        ]);

        $invoicesBefore = $this->clientPrimaryInvoiceCount();
        $scheduler->service()->runTask();

        $this->assertSame($invoicesBefore + 1, $this->clientPrimaryInvoiceCount());

        $newInvoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $this->client->id)
            ->orderByDesc('id')
            ->first();

        $taskIdsOnInvoice = $this->collectTaskIdsFromInvoiceLineItems($newInvoice);
        $this->assertCount(1, $taskIdsOnInvoice);
        $this->assertTaskHashedIdInList($inRangeTask->hashed_id, $taskIdsOnInvoice);
        $this->assertTaskHashedIdNotInList($outOfRangeTask->hashed_id, $taskIdsOnInvoice);
        $this->assertTaskHashedIdNotInList($inRangeInvoicedTask->hashed_id, $taskIdsOnInvoice);

        $this->assertSchedulerNextRunMonthly($scheduler);
    }

    #[DataProvider('invoiceOutstandingTasksDateRangeProvider')]
    public function testInvoiceOutstandingTasksRespectsEachDateRange(
        string $frozenAt,
        string $dateRange,
        string $dateInRange,
        string $dateOutOfRange,
        ?string $customStart,
        ?string $customEnd,
    ): void {
        $this->travelTo(Carbon::parse($frozenAt, 'UTC'));

        $inTask = $this->makeIoTBillableTask($this->client, $dateInRange);
        $outTask = $this->makeIoTBillableTask($this->client, $dateOutOfRange);

        $parameters = [
            'date_range' => $dateRange,
            'include_project_tasks' => false,
        ];
        if ($dateRange === EmailStatement::CUSTOM_RANGE) {
            $this->assertNotNull($customStart);
            $this->assertNotNull($customEnd);
            $parameters['start_date'] = $customStart;
            $parameters['end_date'] = $customEnd;
        }

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi($parameters);
        $before = $this->clientPrimaryInvoiceCount();
        $scheduler->service()->runTask();

        $this->assertSame($before + 1, $this->clientPrimaryInvoiceCount());

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $this->client->id)
            ->orderByDesc('id')
            ->first();

        $ids = $this->collectTaskIdsFromInvoiceLineItems($invoice);
        $this->assertTaskHashedIdInList($inTask->hashed_id, $ids);
        $this->assertTaskHashedIdNotInList($outTask->hashed_id, $ids);
    }

    public function testInvoiceOutstandingTasksIncludeProjectTasksWhenEnabled()
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $project = Project::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $onProject = $this->makeIoTBillableTask($this->client, '2026-05-11', null, $project->id);
        $noProject = $this->makeIoTBillableTask($this->client, '2026-05-12');

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi([
            'include_project_tasks' => true,
            'date_range' => EmailStatement::LAST_MONTH,
        ]);

        $before = $this->clientPrimaryInvoiceCount();
        $scheduler->service()->runTask();
        $this->assertSame($before + 1, $this->clientPrimaryInvoiceCount());

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $this->client->id)
            ->orderByDesc('id')
            ->first();

        $ids = $this->collectTaskIdsFromInvoiceLineItems($invoice);
        $this->assertCount(2, $ids);
        $this->assertTaskHashedIdInList($onProject->hashed_id, $ids);
        $this->assertTaskHashedIdInList($noProject->hashed_id, $ids);
    }

    public function testInvoiceOutstandingTasksExcludesProjectTasksWhenDisabled()
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $project = Project::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $withProject = $this->makeIoTBillableTask($this->client, '2026-05-12', null, $project->id);
        $withoutProject = $this->makeIoTBillableTask($this->client, '2026-05-14');

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi([
            'include_project_tasks' => false,
            'date_range' => EmailStatement::LAST_MONTH,
        ]);

        $scheduler->service()->runTask();

        $newInvoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $this->client->id)
            ->orderByDesc('id')
            ->first();

        $taskIdsOnInvoice = $this->collectTaskIdsFromInvoiceLineItems($newInvoice);
        $this->assertCount(1, $taskIdsOnInvoice);
        $this->assertTaskHashedIdInList($withoutProject->hashed_id, $taskIdsOnInvoice);
        $this->assertTaskHashedIdNotInList($withProject->hashed_id, $taskIdsOnInvoice);
    }

    public function testInvoiceOutstandingTasksRespectsClientsFilter()
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $otherClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->makeIoTBillableTask($this->client, '2026-05-10');
        $otherClientTask = $this->makeIoTBillableTask($otherClient, '2026-05-11');

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi([
            'clients' => [$otherClient->hashed_id],
            'date_range' => EmailStatement::LAST_MONTH,
            'include_project_tasks' => false,
        ]);

        $beforePrimary = $this->clientPrimaryInvoiceCount();
        $beforeOther = $this->clientInvoiceCount($otherClient);

        $scheduler->service()->runTask();

        $this->assertSame($beforePrimary, $this->clientPrimaryInvoiceCount());
        $this->assertSame($beforeOther + 1, $this->clientInvoiceCount($otherClient));

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $otherClient->id)
            ->orderByDesc('id')
            ->first();

        $ids = $this->collectTaskIdsFromInvoiceLineItems($invoice);
        $this->assertSame([$otherClientTask->hashed_id], $ids);
    }

    public function testInvoiceOutstandingTasksNoNewInvoiceWhenOnlyOutOfRangeTasks()
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $this->makeIoTBillableTask($this->client, '2026-06-25');
        $this->makeIoTBillableTask($this->client, '2026-04-01');

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi([
            'date_range' => EmailStatement::LAST_MONTH,
        ]);

        $before = $this->clientPrimaryInvoiceCount();
        $scheduler->service()->runTask();
        $this->assertSame($before, $this->clientPrimaryInvoiceCount());
    }

    public function testInvoiceOutstandingTasksSkipsInvoiceWhenBillableDurationIsZero()
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $this->makeIoTBillableTask($this->client, '2026-05-10', null, null, false, false);

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi([
            'date_range' => EmailStatement::LAST_MONTH,
        ]);

        $before = $this->clientPrimaryInvoiceCount();
        $scheduler->service()->runTask();
        $this->assertSame($before, $this->clientPrimaryInvoiceCount());
    }

    public function testInvoiceOutstandingTasksSkipsRunningTaskOnInvoiceLineItems()
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $runningOnly = $this->makeIoTBillableTask($this->client, '2026-05-09', null, null, true, true);
        $billable = $this->makeIoTBillableTask($this->client, '2026-05-11');

        $scheduler = $this->createInvoiceOutstandingTasksSchedulerViaApi([
            'date_range' => EmailStatement::LAST_MONTH,
        ]);

        $before = $this->clientPrimaryInvoiceCount();
        $scheduler->service()->runTask();
        $this->assertSame($before + 1, $this->clientPrimaryInvoiceCount());

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('client_id', $this->client->id)
            ->orderByDesc('id')
            ->first();

        $ids = $this->collectTaskIdsFromInvoiceLineItems($invoice);
        $this->assertTaskHashedIdInList($billable->hashed_id, $ids);
        $this->assertTaskHashedIdNotInList($runningOnly->hashed_id, $ids);
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
        $user->email = rand(5,555555).'@gmail.com';
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
        $user->email = rand(5,555555).'@gmail.com';
        $user->save();

        $this->assertNotNull($scheduler);

        (new EmailReport($scheduler))->run();

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
        $method = $reflectionMethod->invoke($service_object, $this->client);

        $this->assertIsArray($method);

        $this->assertEquals(EmailStatement::LAST_MONTH, $scheduler->parameters['date_range']);

        $this->assertSameTwoDateStringsOrderIndependent(['2022-12-01', '2022-12-31'], $method);
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
        $method = $reflectionMethod->invoke($service_object, $this->client);

        $this->assertIsArray($method);

        $this->assertEquals('paid', $method['status']);
    }

    public function testGetThisMonthRange()
    {
        $this->travelTo(Carbon::parse('2023-01-14'));

        $this->assertSameTwoDateStringsOrderIndependent(['2023-01-01', '2023-01-31'], $this->getDateRange(EmailStatement::THIS_MONTH));
        $this->assertSameTwoDateStringsOrderIndependent(['2023-01-01', '2023-03-31'], $this->getDateRange(EmailStatement::THIS_QUARTER));
        $this->assertSameTwoDateStringsOrderIndependent(['2023-01-01', '2023-12-31'], $this->getDateRange(EmailStatement::THIS_YEAR));

        $this->assertSameTwoDateStringsOrderIndependent(['2022-12-01', '2022-12-31'], $this->getDateRange(EmailStatement::LAST_MONTH));
        $this->assertSameTwoDateStringsOrderIndependent(['2022-10-01', '2022-12-31'], $this->getDateRange(EmailStatement::LAST_QUARTER));
        $this->assertSameTwoDateStringsOrderIndependent(['2022-01-01', '2022-12-31'], $this->getDateRange(EmailStatement::LAST_YEAR));

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



    public function testInvoiceWithNoExistingScheduleAllowsCreation()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => 100.00,
            'balance' => 100.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $data = [
            'name' => 'Test no existing schedule',
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
                        'amount' => 100,
                        'is_amount' => true,
                    ],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);
    }

    public function testInvoiceWithExistingScheduleBlocksCreation()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => 100.00,
            'balance' => 100.00,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $data = [
            'name' => 'First schedule',
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
                        'amount' => 100,
                        'is_amount' => true,
                    ],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(200);

        // Attempt to create a second schedule for the same invoice - should fail
        $data['name'] = 'Second schedule';

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);

        $response->assertStatus(422);
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
