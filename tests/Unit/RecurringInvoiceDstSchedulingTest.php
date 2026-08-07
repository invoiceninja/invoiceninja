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
use Illuminate\Support\Carbon;
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

    private function configureSchedule(string $timezoneName, int $sendTime): void
    {
        $timezone = app('timezones')->firstWhere('name', $timezoneName);

        $this->assertNotNull($timezone);

        $settings = $this->company->settings;
        $settings->timezone_id = (string) $timezone->id;
        $settings->entity_send_time = $sendTime;

        $this->company->settings = $settings;
        $this->company->save();

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
