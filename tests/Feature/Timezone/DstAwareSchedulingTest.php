<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Timezone;

use App\DataMapper\ClientSettings;
use App\Factory\SchedulerFactory;
use App\Http\Requests\TaskScheduler\PaymentScheduleRequest;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Repositories\SchedulerRepository;
use Illuminate\Http\Request;
use App\Services\Scheduler\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class DstAwareSchedulingTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set(config('app.timezone'));

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, int, string, string, string}>
     */
    public static function recurringInvoiceUtcProvider(): array
    {
        return [
            'northern standard time to daylight time' => [
                'America/Chicago',
                1,
                '2026-03-26',
                '2025-12-26 10:00:00',
                '2026-03-27 06:00:00',
            ],
            'northern daylight time to standard time' => [
                'America/Chicago',
                1,
                '2026-11-14',
                '2026-07-15 10:00:00',
                '2026-11-15 07:00:00',
            ],
            'southern standard time to daylight time' => [
                'Australia/Sydney',
                1,
                '2026-10-14',
                '2026-07-01 10:00:00',
                '2026-10-14 14:00:00',
            ],
            'zero send time retains midnight UTC semantics' => [
                'America/Chicago',
                0,
                '2026-08-10',
                '2026-01-15 10:00:00',
                '2026-08-11 00:00:00',
            ],
            'send time twenty four retains local end of day semantics' => [
                'America/New_York',
                24,
                '2026-07-14',
                '2026-07-01 10:00:00',
                '2026-07-16 03:59:50',
            ],
            'quarter hour timezone' => [
                'Asia/Kathmandu',
                9,
                '2026-04-01',
                '2026-01-15 10:00:00',
                '2026-04-02 03:15:00',
            ],
            'positive UTC offset crosses to previous UTC date' => [
                'Australia/Sydney',
                1,
                '2026-01-14',
                '2026-01-01 10:00:00',
                '2026-01-14 14:00:00',
            ],
            'negative UTC offset crosses to next UTC date' => [
                'America/Los_Angeles',
                23,
                '2026-07-14',
                '2026-07-01 10:00:00',
                '2026-07-16 06:00:00',
            ],
            'nonexistent spring forward time follows native PHP normalization' => [
                'America/Chicago',
                2,
                '2026-03-07',
                '2026-01-15 10:00:00',
                '2026-03-08 08:00:00',
            ],
            'duplicated fall back time uses the first native PHP occurrence' => [
                'America/Chicago',
                1,
                '2026-10-31',
                '2026-07-15 10:00:00',
                '2026-11-01 06:00:00',
            ],
        ];
    }

    #[DataProvider('recurringInvoiceUtcProvider')]
    public function testRecurringInvoiceNextSendDateUsesTargetDateTimezoneRules(
        string $timezone,
        int $sendHour,
        string $currentClientDate,
        string $calculatedAtUtc,
        string $expectedUtc,
    ): void {
        $this->configureTimezone($timezone, $sendHour);
        $this->travelTo(Carbon::parse($calculatedAtUtc, 'UTC'));

        $recurringInvoice = RecurringInvoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => $currentClientDate . ' 00:00:00',
            'next_send_date_client' => $currentClientDate,
            'remaining_cycles' => -1,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
        ]);

        $this->assertSame($timezone, $recurringInvoice->client->timezone()->name);
        $this->assertSame($sendHour, (int) $recurringInvoice->client->getSetting('entity_send_time'));
        $this->assertUtcDateTime($expectedUtc, $recurringInvoice->nextSendDate());
    }

    /**
     * @return array<string, array{int, string, string, string}>
     */
    public static function recurringFrequencyProvider(): array
    {
        return [
            'daily' => [RecurringInvoice::FREQUENCY_DAILY, '2026-01-31', '2026-02-01', '2026-02-01 14:00:00'],
            'weekly' => [RecurringInvoice::FREQUENCY_WEEKLY, '2026-01-31', '2026-02-07', '2026-02-07 14:00:00'],
            'two weeks' => [RecurringInvoice::FREQUENCY_TWO_WEEKS, '2026-01-31', '2026-02-14', '2026-02-14 14:00:00'],
            'four weeks' => [RecurringInvoice::FREQUENCY_FOUR_WEEKS, '2026-01-31', '2026-02-28', '2026-02-28 14:00:00'],
            'monthly without overflow' => [RecurringInvoice::FREQUENCY_MONTHLY, '2026-01-31', '2026-02-28', '2026-02-28 14:00:00'],
            'two months' => [RecurringInvoice::FREQUENCY_TWO_MONTHS, '2026-01-31', '2026-03-31', '2026-03-31 13:00:00'],
            'three months' => [RecurringInvoice::FREQUENCY_THREE_MONTHS, '2026-01-31', '2026-04-30', '2026-04-30 13:00:00'],
            'four months' => [RecurringInvoice::FREQUENCY_FOUR_MONTHS, '2026-01-31', '2026-05-31', '2026-05-31 13:00:00'],
            'six months' => [RecurringInvoice::FREQUENCY_SIX_MONTHS, '2026-01-31', '2026-07-31', '2026-07-31 13:00:00'],
            'annually' => [RecurringInvoice::FREQUENCY_ANNUALLY, '2026-01-31', '2027-01-31', '2027-01-31 14:00:00'],
            'two years' => [RecurringInvoice::FREQUENCY_TWO_YEARS, '2026-01-31', '2028-01-31', '2028-01-31 14:00:00'],
            'three years' => [RecurringInvoice::FREQUENCY_THREE_YEARS, '2026-01-31', '2029-01-31', '2029-01-31 14:00:00'],
        ];
    }

    #[DataProvider('recurringFrequencyProvider')]
    public function testEveryRecurringFrequencyPreservesTheClientDateAndConvertsItsSendTimeToUtc(
        int $frequencyId,
        string $currentClientDate,
        string $expectedClientDate,
        string $expectedUtc,
    ): void {
        $this->configureTimezone('America/New_York', 9);
        $this->travelTo(Carbon::parse('2026-01-15 10:00:00', 'UTC'));

        $recurringInvoice = RecurringInvoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'frequency_id' => $frequencyId,
            'next_send_date' => $currentClientDate . ' 00:00:00',
            'next_send_date_client' => $currentClientDate,
            'remaining_cycles' => -1,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
        ]);

        $this->assertSame($expectedClientDate, $recurringInvoice->nextSendDateClient()?->toDateString());
        $this->assertUtcDateTime($expectedUtc, $recurringInvoice->nextSendDate());
    }

    public function testRecurringQuoteUsesTargetDateTimezoneRules(): void
    {
        $this->configureTimezone('America/Chicago', 1);
        $this->travelTo(Carbon::parse('2025-12-26 10:00:00', 'UTC'));

        $recurringQuote = RecurringQuote::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'frequency_id' => RecurringQuote::FREQUENCY_DAILY,
            'next_send_date' => '2026-03-26 07:00:00',
            'remaining_cycles' => -1,
            'status_id' => RecurringQuote::STATUS_ACTIVE,
        ]);

        $this->assertUtcDateTime('2026-03-27 06:00:00', $recurringQuote->nextSendDate());
    }

    public function testRecurringExpenseUsesTargetDateTimezoneRules(): void
    {
        $this->configureTimezone('America/Chicago', 1);
        $this->travelTo(Carbon::parse('2025-12-26 10:00:00', 'UTC'));

        $recurringExpense = RecurringExpense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => '2026-03-26 07:00:00',
            'next_send_date_client' => '2026-03-26',
            'remaining_cycles' => -1,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
        ]);

        $this->assertUtcDateTime(
            '2026-03-27 06:00:00',
            $recurringExpense->nextDateByFrequency(Carbon::parse('2026-03-26', 'UTC')),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function schedulerDstProvider(): array
    {
        return [
            'standard time to daylight time' => ['2025-12-26 10:00:00', '2026-03-26 06:00:00'],
            'daylight time to standard time' => ['2026-08-15 10:00:00', '2026-11-15 07:00:00'],
        ];
    }

    #[DataProvider('schedulerDstProvider')]
    public function testSchedulerUsesTargetDateTimezoneRules(string $calculatedAtUtc, string $expectedUtc): void
    {
        $this->configureTimezone('America/Chicago', 1);
        $this->travelTo(Carbon::parse($calculatedAtUtc, 'UTC'));

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        $scheduler->frequency_id = RecurringInvoice::FREQUENCY_THREE_MONTHS;
        $scheduler->remaining_cycles = -1;
        $scheduler->next_run = now();
        $scheduler->next_run_client = now()->toDateString();
        $scheduler->save();

        $scheduler->calculateNextRun();

        $this->assertUtcDateTime($expectedUtc, $scheduler->fresh()->next_run);
    }

    public function testSchedulerRepositoryConvertsTheCompanyLocalCursorToUtc(): void
    {
        $this->configureTimezone('America/New_York', 24);

        $scheduler = (new SchedulerRepository())->save([
            'name' => 'UTC boundary scheduler',
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_run' => '2026-07-04',
            'next_run_client' => '2026-07-03',
            'template' => 'email_report',
            'parameters' => [],
            'remaining_cycles' => -1,
        ], SchedulerFactory::create($this->company->id, $this->user->id));

        $this->assertSame('2026-07-03', $scheduler->next_run_client?->toDateString());
        $this->assertUtcDateTime('2026-07-04 03:59:50', $scheduler->next_run);
    }

    public function testPaymentScheduleRequestPreservesTheSubmittedCompanyLocalDate(): void
    {
        $this->configureTimezone('America/New_York', 24);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => '2026-07-01',
            'due_date' => '2026-07-03',
            'amount' => 100,
            'balance' => 100,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $request = PaymentScheduleRequest::create('/', 'POST', [
            'next_run' => '2026-07-03',
            'schedule' => [
                ['date' => '2026-07-03', 'amount' => 100, 'is_amount' => true],
            ],
            'auto_bill' => false,
        ]);
        $request->setRouteResolver(static fn(): object => new class ($invoice) {
            public function __construct(private readonly Invoice $invoice) {}

            public function parameter(string $key, mixed $default = null): mixed
            {
                return $key === 'invoice' ? $this->invoice : $default;
            }
        });

        $request->prepareForValidation();

        $this->assertSame('2026-07-03', $request->input('next_run'));
        $this->assertSame('2026-07-03', $request->input('next_run_client'));
    }

    public function testInvoiceReminderStoresTheExactTargetDateUtcInstant(): void
    {
        $settings = $this->configureTimezone('America/Chicago', 1, [
            'send_reminders' => true,
            'enable_reminder1' => true,
            'enable_reminder2' => false,
            'enable_reminder3' => false,
            'enable_reminder_endless' => false,
            'schedule_reminder1' => 'after_invoice_date',
            'num_days_reminder1' => 91,
        ]);
        $this->travelTo(Carbon::parse('2025-12-26 10:00:00', 'UTC'));

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => '2025-12-26',
            'due_date' => '2028-12-25',
            'last_sent_date' => '2025-12-26 10:00:00',
            'amount' => 100,
            'balance' => 100,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $invoice->service()->setReminder($settings)->save();

        $this->assertUtcDateTime('2026-03-27 06:00:00', $invoice->fresh()->next_send_date);
    }

    public function testQuoteReminderStoresOneExactTargetDateUtcInstant(): void
    {
        $settings = $this->configureTimezone('America/Chicago', 1, [
            'enable_quote_reminder1' => true,
            'quote_schedule_reminder1' => 'after_quote_date',
            'quote_num_days_reminder1' => 91,
        ]);
        $this->travelTo(Carbon::parse('2025-12-26 10:00:00', 'UTC'));

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => '2025-12-26',
            'due_date' => '2028-12-25',
            'last_sent_date' => '2025-12-26 10:00:00',
            'amount' => 100,
            'balance' => 100,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $quote->service()->setReminder($settings)->save();

        $this->assertUtcDateTime('2026-03-27 06:00:00', $quote->fresh()->next_send_date);
    }

    public function testPaymentScheduleStoresItsNextClientDateAsTheExactUtcInstant(): void
    {
        $this->configureTimezone('America/Chicago', 1);
        $this->travelTo(Carbon::parse('2025-12-26 10:00:00', 'UTC'));

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => '2025-12-26',
            'due_date' => '2026-06-27',
            'amount' => 200,
            'balance' => 200,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        $scheduler->template = 'payment_schedule';
        $scheduler->parameters = [
            'invoice_id' => $invoice->hashed_id,
            'auto_bill' => false,
            'schedule' => [
                ['id' => 1, 'date' => '2026-03-27', 'amount' => 100, 'is_amount' => true],
                ['id' => 2, 'date' => '2026-06-27', 'amount' => 100, 'is_amount' => true],
            ],
        ];
        $scheduler->next_run = '2026-03-27';
        $scheduler->next_run_client = '2026-03-27';
        $scheduler->save();

        (new PaymentSchedule($scheduler))->seed();

        $scheduler->refresh();

        $this->assertSame('2026-06-27', $scheduler->next_run_client?->toDateString());
        $this->assertUtcDateTime('2026-06-27 06:00:00', $scheduler->next_run);
    }

    public function testPaymentScheduleRunUsesTheCompanyLocalCursorWhenTheUtcDateDiffers(): void
    {
        $this->configureTimezone('America/New_York', 24);

        $clientTimezone = app('timezones')->first(
            static fn($timezone): bool => $timezone->name === 'Asia/Kathmandu',
        );

        if (! $clientTimezone) {
            throw new RuntimeException('Timezone Asia/Kathmandu is not configured.');
        }

        $clientSettings = $this->client->settings ?? ClientSettings::defaults();
        $clientSettings = clone $clientSettings;
        $clientSettings->timezone_id = (string) $clientTimezone->id;
        $clientSettings->entity_send_time = 9;
        $this->client->settings = $clientSettings;
        $this->client->save();

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => '2026-03-01',
            'due_date' => '2026-11-01',
            'amount' => 300,
            'balance' => 300,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $scheduler = SchedulerFactory::create($this->company->id, $this->user->id);
        $scheduler->template = 'payment_schedule';
        $scheduler->parameters = [
            'invoice_id' => $invoice->hashed_id,
            'auto_bill' => false,
            'schedule' => [
                ['id' => 1, 'date' => '2026-03-07', 'amount' => 100, 'is_amount' => true],
                ['id' => 2, 'date' => '2026-03-08', 'amount' => 100, 'is_amount' => true],
                ['id' => 3, 'date' => '2026-11-01', 'amount' => 100, 'is_amount' => true],
            ],
        ];
        $scheduler->next_run = '2026-03-07';
        $scheduler->next_run_client = '2026-03-07';
        $scheduler->save();

        (new PaymentSchedule($scheduler))->seed();

        $scheduler->refresh();
        $this->assertSame('2026-03-08', $scheduler->next_run_client?->toDateString());
        $this->assertUtcDateTime('2026-03-09 03:59:50', $scheduler->next_run);

        $this->travelTo(Carbon::parse('2026-03-09 04:00:00', 'UTC'));
        $scheduler->service()->runTask();

        $invoice->refresh();
        $scheduler->refresh();

        $this->assertEquals(200, $invoice->partial);
        $this->assertSame('2026-03-08', $invoice->partial_due_date?->toDateString());
        $this->assertSame('2026-11-01', $scheduler->next_run_client?->toDateString());
        $this->assertUtcDateTime('2026-11-02 04:59:50', $scheduler->next_run);
    }

    public function testRecurringServiceConvertsTheSelectedClientDateToTheExactUtcInstant(): void
    {
        $this->configureTimezone('America/Chicago', 1);
        $this->travelTo(Carbon::parse('2026-07-15 10:00:00', 'UTC'));

        $recurringInvoice = RecurringInvoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => '2026-11-15 06:00:00',
            'next_send_date_client' => '2026-11-15',
            'remaining_cycles' => -1,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
        ]);

        $recurringInvoice->service()->triggeredActions(Request::create('/', 'POST'));

        $this->assertUtcDateTime('2026-11-15 07:00:00', $recurringInvoice->next_send_date);
    }

    public function testSynchronizingSendTimeRecomputesTheFutureClientDateAsTheExactUtcInstant(): void
    {
        $this->configureTimezone('America/Chicago', 1);
        $this->travelTo(Carbon::parse('2026-07-15 10:00:00', 'UTC'));

        $recurringInvoice = RecurringInvoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => '2026-11-15 06:00:00',
            'next_send_date_client' => '2026-11-15',
            'remaining_cycles' => -1,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/companies/' . $this->company->hashed_id, [
            'sync_send_time' => 'true',
        ]);

        $response->assertStatus(200);
        $this->assertUtcDateTime('2026-11-15 07:00:00', $recurringInvoice->fresh()->next_send_date);
    }

    public function testSchedulingCalculationsDoNotMutateThePhpDefaultTimezone(): void
    {
        date_default_timezone_set('UTC');
        $this->configureTimezone('America/New_York', 9);

        $recurringInvoice = RecurringInvoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => '2026-07-14 13:00:00',
            'next_send_date_client' => '2026-07-14',
            'remaining_cycles' => -1,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
        ]);

        $recurringInvoice->nextSendDate();

        $this->assertSame('UTC', date_default_timezone_get());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function configureTimezone(string $timezoneName, int $sendHour, array $overrides = []): object
    {
        $timezone = app('timezones')->first(
            static fn($timezone): bool => $timezone->name === $timezoneName,
        );

        if (! $timezone) {
            throw new RuntimeException("Timezone {$timezoneName} is not configured.");
        }

        $settings = clone $this->company->settings;
        $settings->timezone_id = (string) $timezone->id;
        $settings->entity_send_time = $sendHour;

        foreach ($overrides as $setting => $value) {
            $settings->{$setting} = $value;
        }

        $this->company->saveSettings((array) $settings, $this->company);
        $this->client->group_settings_id = null;
        $this->client->save();
        $this->company = $this->company->fresh();
        $this->client = $this->client->fresh();

        return $this->company->settings;
    }

    private function assertUtcDateTime(string $expected, mixed $actual): void
    {
        $this->assertNotNull($actual);

        $actualUtc = Carbon::parse($actual);

        $this->assertSame(0, $actualUtc->getOffset());
        $this->assertSame($expected, $actualUtc->format('Y-m-d H:i:s'));
    }
}
