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

namespace Tests\Feature;

use App\Factory\InvoiceToRecurringInvoiceFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Jobs\Cron\RecurringInvoicesCron;
use App\Models\Client;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class RecurringInvoiceDstSchedulingFeatureTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->makeTestData();
    }

    public function test_api_create_and_update_preserve_the_client_date_and_store_target_date_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 00:00:00', 'UTC'));

        try {
            $this->configureCompanySchedule('America/New_York', 6);
            $this->configureClientSchedule($this->client, 'America/New_York', 6);

            $createResponse = $this->withApiHeaders()->postJson('/api/v1/recurring_invoices', [
                'client_id' => $this->client->hashed_id,
                'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
                'next_send_date' => '2027-11-07',
            ]);

            $createResponse->assertOk();

            $this->assertSame(
                '2027-11-07',
                Carbon::parse($createResponse->json('data.next_send_date'))->toDateString()
            );
            $this->assertSame(
                '2027-11-07 11:00:00',
                Carbon::parse($createResponse->json('data.next_send_datetime'))->utc()->format('Y-m-d H:i:s')
            );

            $recurringInvoice = RecurringInvoice::query()->findOrFail(
                $this->decodePrimaryKey($createResponse->json('data.id'))
            );

            $this->assertStoredSchedule($recurringInvoice, '2027-11-07', '2027-11-07 11:00:00');

            $updateResponse = $this->withApiHeaders()->putJson(
                '/api/v1/recurring_invoices/'.$recurringInvoice->hashed_id,
                ['next_send_date' => '2027-03-14']
            );

            $updateResponse->assertOk();

            $this->assertSame(
                '2027-03-14',
                Carbon::parse($updateResponse->json('data.next_send_date'))->toDateString()
            );
            $this->assertSame(
                '2027-03-14 10:00:00',
                Carbon::parse($updateResponse->json('data.next_send_datetime'))->utc()->format('Y-m-d H:i:s')
            );

            $this->assertStoredSchedule(
                $recurringInvoice->refresh(),
                '2027-03-14',
                '2027-03-14 10:00:00'
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_company_sync_send_time_recalculates_inherited_and_client_overridden_schedules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 00:00:00', 'UTC'));

        try {
            $this->configureCompanySchedule('America/New_York', 6);

            $inheritedClient = Client::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'group_settings_id' => null,
                'settings' => (object) [],
            ]);

            $this->configureClientSchedule($this->client, 'Australia/Sydney', 24);

            $inheritedSchedule = $this->makeActiveRecurringInvoice(
                $inheritedClient,
                '2027-11-07',
                '2027-11-07 00:00:01'
            );
            $overriddenSchedule = $this->makeActiveRecurringInvoice(
                $this->client,
                '2027-11-07',
                '2027-11-07 00:00:01'
            );
            $scheduleWithoutClientDate = $this->makeActiveRecurringInvoice(
                $this->client,
                '2027-11-07',
                '2027-11-07 00:00:01'
            );
            $scheduleWithoutClientDate->next_send_date_client = null;
            $scheduleWithoutClientDate->save();

            $this->withApiHeaders()
                ->putJson('/api/v1/companies/'.$this->company->hashed_id, [
                    'sync_send_time' => 'true',
                ])
                ->assertOk();

            $this->assertStoredSchedule(
                $inheritedSchedule->refresh(),
                '2027-11-07',
                '2027-11-07 11:00:00'
            );
            $this->assertStoredSchedule(
                $overriddenSchedule->refresh(),
                '2027-11-07',
                '2027-11-07 12:59:50'
            );
            $this->assertNull($scheduleWithoutClientDate->refresh()->next_send_date_client);
            $this->assertSame(
                '2027-11-07 00:00:01',
                Carbon::parse($scheduleWithoutClientDate->next_send_date, 'UTC')->format('Y-m-d H:i:s')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cron_waits_until_the_exact_utc_instant_then_advances_across_dst(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-03-13 10:59:59', 'UTC'));

        try {
            RecurringInvoice::query()->cursor()->each(
                static fn (RecurringInvoice $recurringInvoice) => $recurringInvoice->forceDelete()
            );

            $this->configureCompanySchedule('America/New_York', 6);
            $this->configureClientSchedule($this->client, 'America/New_York', 6);

            $clientSettings = $this->client->settings;
            $clientSettings->auto_email_invoice = false;
            $this->client->settings = $clientSettings;
            $this->client->save();

            $recurringInvoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
            $recurringInvoice->user_id = $this->user->id;
            $recurringInvoice->company_id = $this->company->id;
            $recurringInvoice->client_id = $this->client->id;
            $recurringInvoice->number = $this->getNextRecurringInvoiceNumber($this->client, $this->invoice);
            $recurringInvoice->status_id = RecurringInvoice::STATUS_ACTIVE;
            $recurringInvoice->frequency_id = RecurringInvoice::FREQUENCY_DAILY;
            $recurringInvoice->remaining_cycles = 5;
            $recurringInvoice->next_send_date_client = '2027-03-13';
            $recurringInvoice->next_send_date = '2027-03-13 11:00:00';
            $recurringInvoice->last_sent_date = '2027-03-12';
            $recurringInvoice->save();

            (new RecurringInvoicesCron())->handle();

            $this->assertSame(0, $recurringInvoice->invoices()->count());
            $this->assertStoredSchedule(
                $recurringInvoice->refresh(),
                '2027-03-13',
                '2027-03-13 11:00:00'
            );

            Carbon::setTestNow(Carbon::parse('2027-03-13 11:00:00', 'UTC'));

            (new RecurringInvoicesCron())->handle();

            $recurringInvoice->refresh();

            $this->assertSame(1, $recurringInvoice->invoices()->count());
            $this->assertStoredSchedule(
                $recurringInvoice,
                '2027-03-14',
                '2027-03-14 10:00:00'
            );
        } finally {
            Carbon::setTestNow();
        }
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

    private function configureClientSchedule(Client $client, string $timezoneName, int $sendTime): void
    {
        $timezone = app('timezones')->firstWhere('name', $timezoneName);

        $this->assertNotNull($timezone);

        $settings = $client->settings;
        $settings->timezone_id = (string) $timezone->id;
        $settings->entity_send_time = $sendTime;

        $client->settings = $settings;
        $client->save();
        $client->refresh();
    }

    private function makeActiveRecurringInvoice(
        Client $client,
        string $clientDate,
        string $incorrectUtcDateTime
    ): RecurringInvoice {
        $recurringInvoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurringInvoice->client_id = $client->id;
        $recurringInvoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurringInvoice->frequency_id = RecurringInvoice::FREQUENCY_DAILY;
        $recurringInvoice->remaining_cycles = RecurringInvoice::RECURS_INDEFINITELY;
        $recurringInvoice->next_send_date_client = $clientDate;
        $recurringInvoice->next_send_date = $incorrectUtcDateTime;
        $recurringInvoice->save();

        return $recurringInvoice;
    }

    private function assertStoredSchedule(
        RecurringInvoice $recurringInvoice,
        string $expectedClientDate,
        string $expectedUtcDateTime
    ): void {
        $this->assertSame(
            $expectedClientDate,
            Carbon::parse($recurringInvoice->next_send_date_client)->toDateString()
        );
        $this->assertSame(
            $expectedUtcDateTime,
            Carbon::parse($recurringInvoice->next_send_date, 'UTC')->format('Y-m-d H:i:s')
        );
    }

    private function withApiHeaders(): static
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ]);
    }
}
