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

namespace Tests\Unit;

use App\Factory\RecurringInvoiceFactory;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class RecurringInvoiceDstSchedulingTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function test_new_york_recurring_dates_use_the_target_dates_dst_offset(): void
    {
        $this->configureSchedule('America/New_York', 6);

        $springForward = $this->makeDailyRecurringInvoice('2026-03-07', (string) RecurringInvoice::FREQUENCY_DAILY);
        $this->assertRecurringSchedule(
            $springForward,
            '2026-03-08',
            '2026-03-08 10:00:00',
            'America/New_York'
        );

        $fallBack = $this->makeDailyRecurringInvoice('2026-10-31');
        $this->assertRecurringSchedule(
            $fallBack,
            '2026-11-01',
            '2026-11-01 11:00:00',
            'America/New_York'
        );
    }

    public function test_sydney_recurring_dates_use_the_target_dates_dst_offset(): void
    {
        $this->configureSchedule('Australia/Sydney', 6);

        $fallBack = $this->makeDailyRecurringInvoice('2026-04-04');
        $this->assertRecurringSchedule(
            $fallBack,
            '2026-04-05',
            '2026-04-04 20:00:00',
            'Australia/Sydney'
        );

        $springForward = $this->makeDailyRecurringInvoice('2026-10-03');
        $this->assertRecurringSchedule(
            $springForward,
            '2026-10-04',
            '2026-10-03 19:00:00',
            'Australia/Sydney'
        );
    }

    public function test_send_time_zero_remains_utc_midnight(): void
    {
        $this->configureSchedule('America/New_York', 0);

        $recurringInvoice = $this->makeDailyRecurringInvoice('2026-12-14');
        $nextClientDate = $recurringInvoice->nextSendDateClient();
        $nextSendDateUtc = $recurringInvoice->nextSendDate();

        $this->assertNotNull($nextClientDate);
        $this->assertNotNull($nextSendDateUtc);
        $this->assertSame('2026-12-15', $nextClientDate->toDateString());
        $this->assertSame('2026-12-15 00:00:00', $nextSendDateUtc->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $nextSendDateUtc->getTimezone()->getName());
    }

    public function test_send_time_twenty_four_remains_ten_seconds_before_local_midnight(): void
    {
        $this->configureSchedule('America/New_York', 24);

        $recurringInvoice = $this->makeDailyRecurringInvoice('2026-12-14');
        $nextClientDate = $recurringInvoice->nextSendDateClient();
        $nextSendDateUtc = $recurringInvoice->nextSendDate();

        $this->assertNotNull($nextClientDate);
        $this->assertNotNull($nextSendDateUtc);
        $this->assertSame('2026-12-15', $nextClientDate->toDateString());
        $this->assertSame('2026-12-16 04:59:50', $nextSendDateUtc->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-12-15 23:59:50',
            $nextSendDateUtc->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s')
        );
    }

    public function test_nonexistent_spring_forward_time_is_normalized_by_the_timezone(): void
    {
        $this->configureSchedule('Australia/Sydney', 2);

        $scheduledDateUtc = $this->client->scheduledDateTimeUtc('2026-10-04');

        $this->assertSame('2026-10-03 16:00:00', $scheduledDateUtc->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-10-04 03:00:00',
            $scheduledDateUtc->copy()->setTimezone('Australia/Sydney')->format('Y-m-d H:i:s')
        );
    }

    public function test_repeated_fall_back_time_uses_the_first_native_timezone_occurrence(): void
    {
        $this->configureSchedule('America/New_York', 1);

        $scheduledDateUtc = $this->client->scheduledDateTimeUtc('2026-11-01');

        $this->assertSame('2026-11-01 05:00:00', $scheduledDateUtc->format('Y-m-d H:i:s'));
        $this->assertSame('-04:00', $scheduledDateUtc->copy()->setTimezone('America/New_York')->format('P'));
    }

    public function test_non_hour_timezone_offset_is_preserved(): void
    {
        $this->configureSchedule('Asia/Kathmandu', 6);

        $scheduledDateUtc = $this->client->scheduledDateTimeUtc('2026-12-15');

        $this->assertSame('2026-12-15 00:15:00', $scheduledDateUtc->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-12-15 06:00:00',
            $scheduledDateUtc->copy()->setTimezone('Asia/Kathmandu')->format('Y-m-d H:i:s')
        );
    }

    #[DataProvider('frequencyProvider')]
    public function test_every_frequency_preserves_the_expected_client_date(
        int $frequencyId,
        string $expectedClientDate
    ): void {
        $this->configureSchedule('America/New_York', 6);

        $recurringInvoice = $this->makeDailyRecurringInvoice('2024-01-31', $frequencyId);
        $nextClientDate = $recurringInvoice->nextSendDateClient();
        $nextSendDateUtc = $recurringInvoice->nextSendDate();

        $this->assertNotNull($nextClientDate);
        $this->assertNotNull($nextSendDateUtc);
        $this->assertSame($expectedClientDate, $nextClientDate->toDateString());
        $this->assertSame(
            $expectedClientDate,
            $nextSendDateUtc->copy()->setTimezone('America/New_York')->toDateString()
        );
        $this->assertSame(
            '06:00:00',
            $nextSendDateUtc->copy()->setTimezone('America/New_York')->format('H:i:s')
        );
    }

    public static function frequencyProvider(): array
    {
        return [
            'daily' => [RecurringInvoice::FREQUENCY_DAILY, '2024-02-01'],
            'weekly' => [RecurringInvoice::FREQUENCY_WEEKLY, '2024-02-07'],
            'two weeks' => [RecurringInvoice::FREQUENCY_TWO_WEEKS, '2024-02-14'],
            'four weeks' => [RecurringInvoice::FREQUENCY_FOUR_WEEKS, '2024-02-28'],
            'monthly at leap-year month end' => [RecurringInvoice::FREQUENCY_MONTHLY, '2024-02-29'],
            'two months' => [RecurringInvoice::FREQUENCY_TWO_MONTHS, '2024-03-31'],
            'three months' => [RecurringInvoice::FREQUENCY_THREE_MONTHS, '2024-04-30'],
            'four months' => [RecurringInvoice::FREQUENCY_FOUR_MONTHS, '2024-05-31'],
            'six months' => [RecurringInvoice::FREQUENCY_SIX_MONTHS, '2024-07-31'],
            'annually' => [RecurringInvoice::FREQUENCY_ANNUALLY, '2025-01-31'],
            'two years' => [RecurringInvoice::FREQUENCY_TWO_YEARS, '2026-01-31'],
            'three years' => [RecurringInvoice::FREQUENCY_THREE_YEARS, '2027-01-31'],
        ];
    }

    public function test_database_datetime_input_is_treated_as_the_same_client_date_as_date_only_input(): void
    {
        $this->configureSchedule('America/New_York', 6);

        $dateOnly = $this->client->scheduledDateTimeUtc('2026-11-01');
        $databaseDateTime = $this->client->scheduledDateTimeUtc('2026-11-01 18:47:23');

        $this->assertSame('2026-11-01 11:00:00', $dateOnly->format('Y-m-d H:i:s'));
        $this->assertTrue($dateOnly->equalTo($databaseDateTime));
    }

    public function test_company_schedule_is_inherited_when_the_client_has_no_override(): void
    {
        $this->configureCompanySchedule('America/New_York', 6);
        $this->client->group_settings_id = null;
        $this->client->settings = (object) [];
        $this->client->save();
        $this->client->refresh();

        $scheduledDateUtc = $this->client->scheduledDateTimeUtc('2026-11-01');

        $this->assertSame('America/New_York', $this->client->timezone()->name);
        $this->assertSame(6, $this->client->getSetting('entity_send_time'));
        $this->assertSame('2026-11-01 11:00:00', $scheduledDateUtc->format('Y-m-d H:i:s'));
    }

    public function test_client_schedule_overrides_the_company_schedule(): void
    {
        $this->configureCompanySchedule('America/New_York', 6);
        $this->configureClientSchedule('Australia/Sydney', 24);

        $scheduledDateUtc = $this->client->scheduledDateTimeUtc('2026-12-15');

        $this->assertSame('Australia/Sydney', $this->client->timezone()->name);
        $this->assertSame(24, $this->client->getSetting('entity_send_time'));
        $this->assertSame('2026-12-15 12:59:50', $scheduledDateUtc->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-12-15 23:59:50',
            $scheduledDateUtc->copy()->setTimezone('Australia/Sydney')->format('Y-m-d H:i:s')
        );
    }

    public function test_null_client_date_and_invalid_frequency_do_not_create_a_schedule(): void
    {
        $this->configureSchedule('America/New_York', 6);

        $missingDate = $this->makeDailyRecurringInvoice('2026-01-01');
        $missingDate->next_send_date_client = null;

        $invalidFrequency = $this->makeDailyRecurringInvoice('2026-01-01', 999);

        $this->assertNull($missingDate->nextSendDateClient());
        $this->assertNull($missingDate->nextSendDate());
        $this->assertNull($invalidFrequency->nextSendDateClient());
        $this->assertNull($invalidFrequency->nextSendDate());
    }

    public function test_stop_on_unpaid_recovery_restarts_from_today_and_persists_a_consistent_pair(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-07 12:00:00', 'UTC'));

        try {
            $this->configureSchedule('America/New_York', 6);
            $this->company->stop_on_unpaid_recurring = true;
            $this->company->save();

            $recurringInvoice = $this->makeDailyRecurringInvoice('2026-02-01');
            $recurringInvoice->next_send_date = '2026-02-01 11:00:00';

            $nextSendDateUtc = $recurringInvoice->nextSendDate();
            $nextClientDate = $recurringInvoice->nextSendDateClient();

            $this->assertNotNull($nextSendDateUtc);
            $this->assertNotNull($nextClientDate);
            $this->assertSame('2026-03-08', $nextClientDate->toDateString());
            $this->assertSame('2026-03-08 10:00:00', $nextSendDateUtc->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_shared_recurring_service_converts_a_client_backed_entity_and_leaves_one_without_a_client_unchanged(): void
    {
        $this->configureSchedule('America/New_York', 6);
        $request = Request::create('/', 'POST');

        $clientBackedExpense = $this->recurring_expense;
        $clientBackedExpense->client_id = $this->client->id;
        $clientBackedExpense->next_send_date_client = '2026-11-01';
        $clientBackedExpense->next_send_date = '2026-11-01 00:00:00';
        $clientBackedExpense->service()->triggeredActions($request);

        $expenseWithoutClient = $this->recurring_expense->replicate();
        $expenseWithoutClient->client_id = null;
        $expenseWithoutClient->unsetRelation('client');
        $expenseWithoutClient->next_send_date_client = '2026-11-01';
        $expenseWithoutClient->next_send_date = '2026-11-01 00:00:00';
        $expenseWithoutClient->service()->triggeredActions($request);

        $this->assertSame(
            '2026-11-01 11:00:00',
            Carbon::parse($clientBackedExpense->next_send_date)->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-11-01 00:00:00',
            Carbon::parse($expenseWithoutClient->next_send_date)->format('Y-m-d H:i:s')
        );
    }

    private function configureSchedule(string $timezoneName, int $sendTime): void
    {
        $this->configureCompanySchedule($timezoneName, $sendTime);
        $this->configureClientSchedule($timezoneName, $sendTime);
    }

    private function configureCompanySchedule(string $timezoneName, int $sendTime): void
    {
        $timezone = app('timezones')->firstWhere('name', $timezoneName);

        $this->assertNotNull($timezone);

        $settings = $this->company->settings;
        $settings->timezone_id = (string) $timezone->id;
        $settings->entity_send_time = $sendTime;

        $this->company->settings = $settings;
        $this->company->save();
    }

    private function configureClientSchedule(string $timezoneName, int $sendTime): void
    {
        $timezone = app('timezones')->firstWhere('name', $timezoneName);

        $this->assertNotNull($timezone);

        $clientSettings = $this->client->settings;
        $clientSettings->timezone_id = (string) $timezone->id;
        $clientSettings->entity_send_time = $sendTime;

        $this->client->settings = $clientSettings;
        $this->client->save();
        $this->client->refresh();
    }

    private function makeDailyRecurringInvoice(
        string $nextSendDateClient,
        int|string $frequencyId = RecurringInvoice::FREQUENCY_DAILY
    ): RecurringInvoice {
        $recurringInvoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurringInvoice->client_id = $this->client->id;
        $recurringInvoice->frequency_id = $frequencyId;
        $recurringInvoice->next_send_date = Carbon::parse($nextSendDateClient, 'UTC')->startOfDay();
        $recurringInvoice->next_send_date_client = $nextSendDateClient;
        $recurringInvoice->remaining_cycles = RecurringInvoice::RECURS_INDEFINITELY;

        return $recurringInvoice;
    }

    private function assertRecurringSchedule(
        RecurringInvoice $recurringInvoice,
        string $expectedClientDate,
        string $expectedUtcDateTime,
        string $timezoneName
    ): void {
        $nextClientDate = $recurringInvoice->nextSendDateClient();
        $nextSendDateUtc = $recurringInvoice->nextSendDate();

        $this->assertNotNull($nextClientDate);
        $this->assertNotNull($nextSendDateUtc);
        $this->assertSame($expectedClientDate, $nextClientDate->toDateString());
        $this->assertSame($expectedUtcDateTime, $nextSendDateUtc->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $nextSendDateUtc->getTimezone()->getName());
        $this->assertSame(
            $expectedClientDate,
            $nextSendDateUtc->copy()->setTimezone($timezoneName)->toDateString()
        );
    }
}
