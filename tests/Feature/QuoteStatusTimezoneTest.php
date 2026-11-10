<?php

namespace Tests\Feature;

use App\Export\CSV\BaseExport;
use App\Filters\QuoteFilters;
use App\Livewire\QuotesTable;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Company;
use App\Models\Timezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

class QuoteStatusTimezoneTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    #[DataProvider('timezoneCases')]
    public function testAdminAndPortalFiltersUseTheirOwnCalendarDate(
        ?string $companyTimezone,
        string $clientTimezone,
        string $instant,
        bool $adminExpired,
        bool $clientExpired,
    ): void {
        $this->travelTo(Carbon::parse($instant, 'UTC'));
        $settings = $this->company->settings;
        $settings->timezone_id = $companyTimezone === null
            ? null
            : Timezone::where('name', $companyTimezone)->firstOrFail()->id;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $settings = $this->client->settings;
        $settings->timezone_id = Timezone::where('name', $clientTimezone)->firstOrFail()->id;
        $this->client->settings = $settings;
        $this->client->saveQuietly();

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'status_id' => Quote::STATUS_SENT,
            'due_date' => '2026-09-09',
        ]);
        $this->actingAs($this->user, 'user');
        $export = new class () extends BaseExport {
            public function quoteStatus(Builder $query, string $status): Builder
            {
                return $this->addQuoteStatusFilter($query, $status);
            }
        };
        $export->company = $this->company;

        foreach (['expired' => $adminExpired, 'sent' => !$adminExpired, 'upcoming' => !$adminExpired] as $status => $expected) {
            $apiQuery = Quote::whereKey($quote->id)->filter(new QuoteFilters(Request::create('/', 'GET', [
                'client_status' => $status,
            ])));
            $this->assertSame($expected, $apiQuery->exists(), "API {$status}");
            $this->assertSame($expected, $export->quoteStatus(Quote::whereKey($quote->id), $status)->exists(), "CSV {$status}");
        }

        $contact = $this->contact->fresh();
        $this->actingAs($contact, 'contact');
        $table = new QuotesTable();
        $method = new ReflectionMethod($table, 'buildQuery');
        foreach ([Quote::STATUS_EXPIRED => $clientExpired, Quote::STATUS_SENT => !$clientExpired] as $status => $expected) {
            $table->status = [(string) $status];
            $query = $method->invoke($table)->whereKey($quote->id);
            $this->assertSame($expected, $query->exists(), "Portal {$status}");
        }

        $table->status = [(string) Quote::STATUS_EXPIRED, (string) Quote::STATUS_SENT];
        $this->assertSame(1, $method->invoke($table)->whereKey($quote->id)->paginate(1)->total());

        // Terminal statuses must not become "expired" merely because of their date.
        $table->status = [(string) Quote::STATUS_EXPIRED];
        foreach ([Quote::STATUS_APPROVED, Quote::STATUS_REJECTED, Quote::STATUS_CANCELLED, Quote::STATUS_CONVERTED] as $status) {
            $quote->forceFill(['status_id' => $status, 'due_date' => '2026-09-01'])->saveQuietly();
            $this->assertFalse($method->invoke($table)->whereKey($quote->id)->exists());
        }

        $quote->forceFill(['status_id' => Quote::STATUS_SENT, 'due_date' => null])->saveQuietly();
        $this->assertFalse($method->invoke($table)->whereKey($quote->id)->exists());
        $table->status = [(string) Quote::STATUS_SENT];
        $this->assertTrue($method->invoke($table)->whereKey($quote->id)->exists());
    }

    public static function timezoneCases(): array
    {
        return [
            'company ahead, client still on due date' => ['Australia/Sydney', 'America/Los_Angeles', '2026-09-09 14:00:00', true, false],
            'company behind, client already past due date' => ['America/Los_Angeles', 'Australia/Sydney', '2026-09-09 14:00:00', false, true],
            'company and client on due date, server tomorrow' => ['America/Los_Angeles', 'America/Los_Angeles', '2026-09-10 00:00:00', false, false],
            'final second of Sydney due date' => ['Australia/Sydney', 'Australia/Sydney', '2026-09-09 13:59:59', false, false],
            'midnight after Sydney due date' => ['Australia/Sydney', 'Australia/Sydney', '2026-09-09 14:00:00', true, true],
            'missing company timezone uses application timezone' => [null, 'Australia/Sydney', '2026-09-09 14:00:00', false, true],
        ];
    }

    public function testCombinedStatusFiltersPreserveCompanyAndClientScopes(): void
    {
        $attributes = [
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'status_id' => Quote::STATUS_APPROVED,
            'due_date' => '2020-01-01',
        ];
        $ownQuote = Quote::factory()->create($attributes);
        $otherClient = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $otherClientQuote = Quote::factory()->create(array_merge($attributes, [
            'client_id' => $otherClient->id,
            'status_id' => Quote::STATUS_SENT,
        ]));
        $otherCompany = Company::factory()->create(['account_id' => $this->company->account_id]);
        $otherCompanyClient = Client::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $this->user->id,
        ]);
        $otherCompanyQuote = Quote::factory()->create(array_merge($attributes, [
            'company_id' => $otherCompany->id,
            'client_id' => $otherCompanyClient->id,
            'status_id' => Quote::STATUS_SENT,
        ]));
        $ids = [$ownQuote->id, $otherClientQuote->id, $otherCompanyQuote->id];

        $this->actingAs($this->user, 'user');
        $apiIds = Quote::whereIn('id', $ids)->filter(new QuoteFilters(Request::create('/', 'GET', [
            'client_status' => 'expired,approved',
        ])))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$ownQuote->id, $otherClientQuote->id], $apiIds);

        $export = new class () extends BaseExport {
            public function quoteStatus(Builder $query, string $status): Builder
            {
                return $this->addQuoteStatusFilter($query, $status);
            }
        };
        $export->company = $this->company;
        $exportIds = $export->quoteStatus(
            Quote::where('company_id', $this->company->id)->whereIn('id', $ids),
            'expired,approved',
        )->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$ownQuote->id, $otherClientQuote->id], $exportIds);

        $this->actingAs($this->contact->fresh(), 'contact');
        $table = new QuotesTable();
        $table->status = [(string) Quote::STATUS_EXPIRED, (string) Quote::STATUS_APPROVED];
        $query = (new ReflectionMethod($table, 'buildQuery'))->invoke($table)->whereIn('id', $ids);
        $this->assertSame([$ownQuote->id], $query->pluck('id')->all());
    }
}
