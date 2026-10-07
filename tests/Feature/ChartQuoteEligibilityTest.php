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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Repositories\QuoteRepository;
use App\Services\Chart\ChartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class ChartQuoteEligibilityTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private Company $metricCompany;
    private Client $metricClient;
    private ChartService $charts;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00 UTC'));
        $this->makeTestData();
        $this->actingAs($this->user);
        $this->configureCompany('Europe/London');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function configureCompany(?string $timezone): void
    {
        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->timezone_id = $timezone === null ? null : (string) app('timezones')->firstWhere('name', $timezone)->id;
        $this->metricCompany = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);
        $clientSettings = ClientSettings::defaults();
        $clientSettings->currency_id = '1';
        $this->metricClient = Client::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'settings' => $clientSettings,
        ]);
        $this->charts = new ChartService($this->metricCompany, $this->user, true);
    }

    private function createQuote(array $attributes = []): Quote
    {
        return Quote::factory()->create(array_merge([
            'company_id' => $this->metricCompany->id,
            'client_id' => $this->metricClient->id,
            'user_id' => $this->user->id,
            'status_id' => Quote::STATUS_SENT,
            'invoice_id' => null,
            'is_deleted' => false,
            'date' => '2026-03-01',
            'due_date' => '2026-12-31',
            'amount' => 100,
            'exchange_rate' => 1,
        ], $attributes));
    }

    private function assertMetrics(int $count, float $amount, int $unapprovedCount, float $unapprovedAmount): void
    {
        foreach (['count' => $count, 'sum' => $amount, 'avg' => $count ? $amount / $count : 0] as $calculation => $expected) {
            $this->assertEquals($expected, $this->charts->getActiveQuotes(['period' => 'total', 'calculation' => $calculation]));
        }
        foreach (['count' => $unapprovedCount, 'sum' => $unapprovedAmount, 'avg' => $unapprovedCount ? $unapprovedAmount / $unapprovedCount : 0] as $calculation => $expected) {
            $this->assertEquals($expected, $this->charts->getUnapprovedQuotes(['period' => 'total', 'calculation' => $calculation]));
        }
        $forecast = $this->charts->getOpenQuotesForForecasting();
        $this->assertCount($count, $forecast);
        $this->assertEquals($amount, array_sum(array_column($forecast, 'amount')));
        $this->assertEquals($amount, array_sum(array_column($this->charts->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 1), 'total')));
        $this->assertEquals($amount, array_sum(array_column($this->charts->getAggregateQuotePipelineChartQuery('2020-01-01', '2030-12-31'), 'total')));
    }

    private function assertAllTimeStartDate(string $expected): void
    {
        $analytics = $this->charts->analytics_summary('2026-06-01', '2026-12-31', true);
        $forecast = $this->charts->cashflow_forecast('2026-06-01', '2026-12-31', 'monthly', true);
        $this->assertSame($expected, $analytics['start_date']);
        $this->assertSame($expected, $forecast['start_date']);
    }

    public static function excludedQuotes(): array
    {
        $cases = [
            'archived quote' => [['deleted_at' => '2026-06-01 00:00:00'], []],
            'deleted quote' => [['is_deleted' => true], []],
            'converted' => [['status_id' => Quote::STATUS_CONVERTED], []],
            'cancelled' => [['status_id' => Quote::STATUS_CANCELLED], []],
            'rejected' => [['status_id' => Quote::STATUS_REJECTED], []],
            'draft' => [['status_id' => Quote::STATUS_DRAFT], []],
            'expired sent' => [['due_date' => '2026-06-14'], []],
            'expired approved' => [['status_id' => Quote::STATUS_APPROVED, 'due_date' => '2026-06-14'], []],
            'archived client' => [[], ['deleted_at' => '2026-06-01 00:00:00']],
            'deleted client' => [[], ['is_deleted' => true]],
            'archived approved quote' => [['status_id' => Quote::STATUS_APPROVED, 'deleted_at' => '2026-06-01 00:00:00'], []],
        ];

        foreach ($cases as $name => [$quoteAttributes, $clientAttributes]) {
            if (! array_key_exists('due_date', $quoteAttributes)) {
                $cases[$name . ' without expiry'] = [array_merge($quoteAttributes, ['due_date' => null]), $clientAttributes];
            }
        }

        return $cases;
    }

    #[DataProvider('excludedQuotes')]
    public function testIneligibleQuotesDoNotAffectMetricsOrAllTimeDates(array $quoteAttributes, array $clientAttributes): void
    {
        if ($clientAttributes) {
            $excludedClient = $this->metricClient->replicate();
            $excludedClient->forceFill($clientAttributes)->save();
            $quoteAttributes['client_id'] = $excludedClient->id;
        }
        $this->createQuote(array_merge(['date' => '2020-01-01', 'amount' => 9999], $quoteAttributes));
        $this->assertMetrics(0, 0, 0, 0);
        $this->assertAllTimeStartDate('2000-01-01');
        $this->createQuote(['due_date' => '2026-06-15']);
        $this->createQuote(['status_id' => Quote::STATUS_APPROVED, 'amount' => 300, 'due_date' => null]);
        $this->assertMetrics(2, 400, 1, 100);
        $this->assertAllTimeStartDate('2026-03-01');
    }

    public static function companyDates(): array
    {
        return [
            'ahead of UTC' => ['Pacific/Auckland', '2026-06-15 12:30:00 UTC', '2026-06-16', '2026-06-15'],
            'behind UTC' => ['America/Los_Angeles', '2026-06-15 00:30:00 UTC', '2026-06-14', '2026-06-13'],
        ];
    }

    #[DataProvider('companyDates')]
    public function testExpiryUsesCompanyDate(string $timezone, string $instant, string $today, string $yesterday): void
    {
        Carbon::setTestNow(Carbon::parse($instant));
        $this->configureCompany($timezone);
        $this->createQuote(['date' => '2020-01-01', 'due_date' => $yesterday, 'amount' => 9999]);
        $this->createQuote(['due_date' => $today]);
        $this->createQuote(['due_date' => null, 'amount' => 300]);
        $this->assertMetrics(2, 400, 2, 400);
        $this->assertAllTimeStartDate('2026-03-01');
    }

    public static function localMidnights(): array
    {
        return [
            'US daylight saving begins' => ['America/Los_Angeles', '2026-03-08'],
            'NZ daylight saving ends' => ['Pacific/Auckland', '2026-04-05'],
        ];
    }

    #[DataProvider('localMidnights')]
    public function testQuoteExpiresAtCompanyMidnight(string $timezone, string $expiry): void
    {
        $this->configureCompany($timezone);
        $beforeMidnight = Carbon::parse($expiry . ' 23:59:59', $timezone);
        Carbon::setTestNow($beforeMidnight);
        $this->createQuote(['due_date' => $expiry]);
        $this->createQuote(['date' => '2026-03-02', 'due_date' => null, 'amount' => 300]);
        $this->assertMetrics(2, 400, 2, 400);
        $this->assertAllTimeStartDate('2026-03-01');

        Carbon::setTestNow($beforeMidnight->copy()->addSecond());
        $this->assertMetrics(1, 300, 1, 300);
        $this->assertAllTimeStartDate('2026-03-02');
    }

    public function testMissingCompanyTimezoneUsesApplicationTimezone(): void
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        Carbon::setTestNow(Carbon::parse('2026-06-15 00:30:00 UTC'));
        $this->configureCompany(null);
        $this->assertNull($this->metricCompany->timezone());
        $this->createQuote(['date' => '2020-01-01', 'due_date' => '2026-06-13', 'amount' => 9999]);
        $this->createQuote(['due_date' => '2026-06-14']);
        $this->assertMetrics(1, 100, 1, 100);
        $this->assertAllTimeStartDate('2026-03-01');
    }

    public static function staleInvoicedQuotes(): array
    {
        return [
            'sent with expiry' => [Quote::STATUS_SENT, '2026-12-31'],
            'approved with expiry' => [Quote::STATUS_APPROVED, '2026-12-31'],
            'sent without expiry' => [Quote::STATUS_SENT, null],
            'approved without expiry' => [Quote::STATUS_APPROVED, null],
        ];
    }

    #[DataProvider('staleInvoicedQuotes')]
    public function testInvoicedQuoteWithStaleStatusCannotExtendAllTimeDates(int $status, ?string $expiry): void
    {
        // A draft invoice does not itself contribute to either all-time date selector.
        $invoice = Invoice::factory()->create([
            'company_id' => $this->metricCompany->id,
            'client_id' => $this->metricClient->id,
            'user_id' => $this->user->id,
            'status_id' => Invoice::STATUS_DRAFT,
            'date' => '2026-06-01',
            'is_deleted' => false,
        ]);
        $this->createQuote([
            'date' => '2020-01-01',
            'due_date' => $expiry,
            'status_id' => $status,
            'invoice_id' => $invoice->id,
        ]);
        $this->assertMetrics(0, 0, 0, 0);
        $this->assertAllTimeStartDate('2000-01-01');
        $this->createQuote();
        $this->assertMetrics(1, 100, 1, 100);
        $this->assertAllTimeStartDate('2026-03-01');
    }

    public function testPipelineCurrencyTotalsExcludeArchivedQuotesAndClients(): void
    {
        $this->createQuote();
        $foreignClient = $this->metricClient->replicate();
        $settings = clone $foreignClient->settings;
        $settings->currency_id = '2';
        $foreignClient->settings = $settings;
        $foreignClient->save();
        $this->createQuote(['client_id' => $foreignClient->id, 'amount' => 600, 'exchange_rate' => 2]);
        $this->createQuote([
            'client_id' => $foreignClient->id,
            'amount' => 9000,
            'exchange_rate' => 2,
            'deleted_at' => now(),
            'due_date' => null,
        ]);
        $archivedClient = $foreignClient->replicate();
        $archivedClient->deleted_at = now();
        $archivedClient->save();
        $this->createQuote(['client_id' => $archivedClient->id, 'amount' => 8000, 'exchange_rate' => 2]);

        $this->assertEquals(100, array_sum(array_column($this->charts->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 1), 'total')));
        $this->assertEquals(600, array_sum(array_column($this->charts->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 2), 'total')));
        $this->assertEquals(400, array_sum(array_column($this->charts->getAggregateQuotePipelineChartQuery('2020-01-01', '2030-12-31'), 'total')));
        $forecast = $this->charts->getOpenQuotesForForecasting();
        $this->assertCount(2, $forecast);
        $this->assertEquals(400, array_sum(array_map(fn ($quote) => $quote->amount / $quote->exchange_rate, $forecast)));
    }

    public function testRestrictedUserForecastAndPipelineRetainOwnershipFilters(): void
    {
        $otherUser = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'other-chart-user@example.com',
        ]);
        $visible = $this->createQuote(['due_date' => null]);
        $this->createQuote(['user_id' => $otherUser->id, 'date' => '2020-01-01', 'due_date' => null, 'amount' => 9000]);
        $this->createQuote(['date' => '2020-01-01', 'deleted_at' => now(), 'due_date' => null, 'amount' => 8000]);
        $archivedClient = $this->metricClient->replicate();
        $archivedClient->deleted_at = now();
        $archivedClient->save();
        $this->createQuote(['client_id' => $archivedClient->id, 'date' => '2020-01-01', 'due_date' => null, 'amount' => 7000]);

        $this->charts = new ChartService($this->metricCompany, $this->user, false);
        $this->assertSame([$visible->id], array_map(fn ($row) => (int) $row->quote_id, $this->charts->getOpenQuotesForForecasting()));
        $this->assertEquals(100, array_sum(array_column($this->charts->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 1), 'total')));
        $this->assertEquals(100, array_sum(array_column($this->charts->getAggregateQuotePipelineChartQuery('2020-01-01', '2030-12-31'), 'total')));
        $this->assertAllTimeStartDate('2026-03-01');

        $this->charts = new ChartService($this->metricCompany, $this->user, true);
        $this->assertCount(2, $this->charts->getOpenQuotesForForecasting());
        $this->assertEquals(9100, array_sum(array_column($this->charts->getAggregateQuotePipelineChartQuery('2020-01-01', '2030-12-31'), 'total')));
        $this->assertAllTimeStartDate('2020-01-01');
    }

    public function testRestoringEligibleQuoteAndClientRestoresMetrics(): void
    {
        $quote = $this->createQuote();
        $repository = new QuoteRepository();
        $repository->archive($quote);
        $this->assertMetrics(0, 0, 0, 0);
        $repository->restore($quote);
        $this->assertMetrics(1, 100, 1, 100);
        $this->metricClient->delete();
        $this->assertMetrics(0, 0, 0, 0);
        $this->metricClient->restore();
        $this->assertMetrics(1, 100, 1, 100);
    }

    public static function archiveSettings(): array
    {
        return ['auto archive enabled' => [true], 'auto archive disabled' => [false]];
    }

    #[DataProvider('archiveSettings')]
    public function testConversionIsExcludedAndPreservedInHistory(bool $autoArchive): void
    {
        $settings = $this->metricClient->settings;
        $settings->auto_archive_quote = $autoArchive;
        $this->metricClient->settings = $settings;
        $this->metricClient->save();
        $quote = $this->createQuote();
        $quote->service()->convert();
        $quote->refresh();
        $this->assertNotNull($quote->invoice_id);
        $this->assertSame($autoArchive, $quote->trashed());
        $this->assertMetrics(0, 0, 0, 0);

        $this->createQuote();
        $history = $this->charts->getQuoteConversionHistory();
        $this->assertEquals(1, $history[0]->converted_quotes);
        $this->assertEquals(0.5, $history[0]->conversion_rate);
        $this->assertEquals(0.5, $this->charts->getOpenQuotesForForecasting()[0]->client_conversion_rate);

        // A stale sent/approved status must not reintroduce an invoiced quote.
        $quote->restore();
        foreach ([Quote::STATUS_SENT, Quote::STATUS_APPROVED] as $status) {
            $quote->status_id = $status;
            $quote->saveQuietly();
            $this->assertMetrics(1, 100, 1, 100);
        }
    }

    public function testCalculatedFieldsApiExcludesArchivedQuotes(): void
    {
        $this->metricCompany = $this->company;
        $this->metricClient = $this->client;
        Quote::withTrashed()->where('company_id', $this->company->id)->update(['is_deleted' => true]);
        $this->createQuote();
        $archived = $this->createQuote(['amount' => 900]);
        (new QuoteRepository())->archive($archived);

        foreach (['active_quotes', 'unapproved_quotes'] as $field) {
            foreach (['count' => 1, 'sum' => 100, 'avg' => 100] as $calculation => $expected) {
                $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/charts/calculated_fields', [
                    'field' => $field,
                    'period' => 'total',
                    'calculation' => $calculation,
                ])->assertOk();
                $this->assertEquals($expected, $response->json());
            }
        }
    }

    public function testPeriodAndCompanyFiltersRemainInEffect(): void
    {
        $this->createQuote();
        $this->createQuote(['date' => '2026-04-01', 'amount' => 300]);
        // makeTestData also created quotes in a different company.
        foreach (['getActiveQuotes', 'getUnapprovedQuotes'] as $method) {
            foreach (['current', 'previous'] as $period) {
                $this->assertEquals(1, $this->charts->$method([
                    'period' => $period,
                    'calculation' => 'count',
                    'start_date' => '2026-03-01',
                    'end_date' => '2026-03-31',
                ]));
                $this->assertEquals(2, $this->charts->$method([
                    'period' => $period,
                    'calculation' => 'count',
                    'date_range' => 'all_time',
                ]));
            }
        }
        $this->assertMetrics(2, 400, 2, 400);
        $this->assertEquals(100, array_sum(array_column($this->charts->getQuotePipelineChartQuery('2026-03-01', '2026-03-31', 1), 'total')));
        $this->assertEquals(100, array_sum(array_column($this->charts->getAggregateQuotePipelineChartQuery('2026-03-01', '2026-03-31'), 'total')));
    }
}
