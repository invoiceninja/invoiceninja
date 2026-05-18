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

use Tests\TestCase;
use App\Models\Expense;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Covers the list filter / sort enhancements:
 *  - new column filters (country_id, custom_value*, project_ids, ...)
 *  - updated_between
 *  - date_range standardisation
 *
 * @see \App\Filters\QueryFilters
 */
class QueryFilterEnhancementsTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
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

    private function headers(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /** Returns the hashed ids present in a list response payload. */
    private function ids(array $arr): array
    {
        return array_column($arr['data'], 'id');
    }

    public function testUnknownFilterParamIsIgnoredAndNotReflected()
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?bogus_param=1')
            ->assertStatus(200);

        $arr = $response->json();

        // Unknown params are silently ignored — never echoed back, so no
        // reflection / amplification surface in the response envelope.
        $this->assertArrayNotHasKey('warnings', $arr['meta'] ?? []);
        // Non-breaking: the unknown param did not filter anything out.
        $this->assertNotEmpty($arr['data']);
    }

    public function testReservedFrameworkParamsAreInert()
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?per_page=20&t=123456&_=1736900000&clear_cache=true&include=')
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertArrayNotHasKey('warnings', $arr['meta'] ?? []);
    }

    public function testLegacyDateRangeOnColumnlessEntityIsSafeNoOp()
    {
        // clients has no `date` column: a 2-part date_range no-ops and the
        // response envelope is unchanged.
        $arr = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?date_range=2020-01-01,2020-12-31')
            ->assertStatus(200)
            ->json();

        $this->assertArrayNotHasKey('warnings', $arr['meta'] ?? []);
    }

    public function testClientCountryIdFilter()
    {
        $this->client->country_id = 8;
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?country_id=8')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?country_id=9')
            ->assertStatus(200)
            ->json();

        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testClientCustomValueFilter()
    {
        $this->client->custom_value1 = 'ZZ-FILTER-TOKEN';
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?custom_value1=ZZ-FILTER-TOKEN')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?custom_value1=NOPE-NOT-PRESENT')
            ->assertStatus(200)
            ->json();

        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testCustomValueFilterIsSafeOnEntityWithoutColumn()
    {
        // payments has no custom_value1 column - column guard => safe no-op, no SQL error.
        $this->withHeaders($this->headers())
            ->get('/api/v1/payments?custom_value1=anything')
            ->assertStatus(200);
    }

    public function testClientNumberIsExactMatched()
    {
        $this->client->number = 'PREFIX-000123';
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?number=PREFIX-000123')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($match));

        // Exact match only — a prefix must NOT match (reverted LIKE change).
        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?number=PREFIX-000')
            ->assertStatus(200)
            ->json();

        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testClientIdNumberIsExactMatched()
    {
        $this->client->id_number = 'ID-MIDDLE-XYZ';
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?id_number=ID-MIDDLE-XYZ')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($match));

        // Exact match only — a substring must NOT match (reverted LIKE change).
        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?id_number=MIDDLE')
            ->assertStatus(200)
            ->json();

        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testExpenseProjectIdsFilter()
    {
        $expense = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
        ]);

        $hash = $this->encodePrimaryKey($expense->id);
        $project_hash = $this->encodePrimaryKey($this->project->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/expenses?project_ids=' . $project_hash)
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($match));
        // The seeded $this->expense has no project_id, so it must be excluded.
        $this->assertNotContains($this->encodePrimaryKey($this->expense->id), $this->ids($match));
    }

    public function testProjectAssignedUserAlias()
    {
        $this->project->assigned_user_id = $this->user->id;
        $this->project->saveQuietly();

        $hash = $this->encodePrimaryKey($this->project->id);

        $arr = $this->withHeaders($this->headers())
            ->get('/api/v1/projects?assigned_user=' . $this->encodePrimaryKey($this->user->id))
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($arr));
    }

    public function testUpdatedBetweenFilter()
    {
        $hash = $this->encodePrimaryKey($this->client->id);

        $past = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?updated_between=2999-01-01,2999-12-31')
            ->assertStatus(200)
            ->json();

        $this->assertNotContains($hash, $this->ids($past));

        $now = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?updated_between=2000-01-01,2999-12-31')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($now));
    }

    public function testDateRangeLegacyTwoPartStillFilters()
    {
        $this->invoice->date = '1971-01-02';
        $this->invoice->saveQuietly();

        $hash = $this->encodePrimaryKey($this->invoice->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?date_range=1971-01-01,1971-01-03')
            ->assertStatus(200)
            ->json();

        // Legacy 2-part still maps to whereBetween('date', ...)
        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?date_range=1972-01-01,1972-12-31')
            ->assertStatus(200)
            ->json();

        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testDateRangeCanonicalThreePartFiltersInvoices()
    {
        // Previously a 3-part date_range was silently ignored on invoices;
        // the unified base now applies it (documented behaviour change).
        $arr = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?date_range=date,2999-01-01,2999-12-31')
            ->assertStatus(200)
            ->json();

        $this->assertCount(0, $arr['data']);
    }

    public function testPaymentLegacyThreePartDateRangeStillWorks()
    {
        // Old PaymentFilters contract: "_,start,end" with a non-column placeholder.
        $this->withHeaders($this->headers())
            ->get('/api/v1/payments?date_range=_,2999-01-01,2999-12-31')
            ->assertStatus(200);
    }

    public function testDueDateRangeLegacyTwoPartStillFilters()
    {
        $this->invoice->due_date = '1971-01-02';
        $this->invoice->saveQuietly();

        $hash = $this->encodePrimaryKey($this->invoice->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date_range=1971-01-01,1971-01-03')
            ->assertStatus(200)
            ->json();

        // Legacy 2-part still maps to whereBetween('due_date', ...)
        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date_range=1972-01-01,1972-12-31')
            ->assertStatus(200)
            ->json();

        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testDueDateRangeCanonicalThreePartFiltersInvoices()
    {
        // The Flutter client sends the 3-part canonical
        // "due_date,start,end"; the base now applies it (previously a
        // 2-part-only guard silently ignored it).
        $this->invoice->due_date = '1971-01-02';
        $this->invoice->saveQuietly();

        $hash = $this->encodePrimaryKey($this->invoice->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date_range=due_date,1971-01-01,1971-01-03')
            ->assertStatus(200)
            ->json();
        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date_range=due_date,2999-01-01,2999-12-31')
            ->assertStatus(200)
            ->json();
        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testDueDateRangeThreePartOnQuotesAndCredits()
    {
        // Inherited base contract — no per-entity override needed.
        $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?due_date_range=due_date,2999-01-01,2999-12-31')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->withHeaders($this->headers())
            ->get('/api/v1/credits?due_date_range=due_date,2999-01-01,2999-12-31')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function testDueDateRangeMalformedIsSafeNoOp()
    {
        // Single part / unparseable bounds → unfiltered set, no 422.
        $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date_range=only-one')
            ->assertStatus(200);

        $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date_range=due_date,not-a-date,also-bad')
            ->assertStatus(200);
    }

    // ── Comparable date / numeric operators (canonical prefix op:value) ──

    public function testCreatedAtPrefixGtComparator()
    {
        $hash = $this->encodePrimaryKey($this->client->id);

        // Client was created "now" → not after the year 2999.
        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=gt:2999-01-01')
            ->assertStatus(200)
            ->json();
        $this->assertNotContains($hash, $this->ids($miss));

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=gt:2000-01-01')
            ->assertStatus(200)
            ->json();
        $this->assertContains($hash, $this->ids($match));
    }

    public function testCreatedAtLteComparator()
    {
        $hash = $this->encodePrimaryKey($this->client->id);

        // Created now → not on-or-before 2000.
        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=lte:2000-01-01')
            ->assertStatus(200)
            ->json();
        $this->assertNotContains($hash, $this->ids($miss));

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=lte:2999-12-31')
            ->assertStatus(200)
            ->json();
        $this->assertContains($hash, $this->ids($match));
    }

    public function testCreatedAtEqIsCalendarDay()
    {
        $this->client->created_at = '2015-06-15 13:45:00';
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        // Date-only eq → half-open [day, day+1) range; the time component
        // is irrelevant, same calendar day matches (index-safe, no whereDate).
        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=eq:2015-06-15')
            ->assertStatus(200)
            ->json();
        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=eq:2015-06-16')
            ->assertStatus(200)
            ->json();
        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testCreatedAtPlainDateStillAppliesGte()
    {
        // Backward compat: a bare date (no op prefix) keeps the historical
        // `created_at >= value` behaviour for other API clients.
        $hash = $this->encodePrimaryKey($this->client->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=2000-01-01')
            ->assertStatus(200)
            ->json();
        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=2999-01-01')
            ->assertStatus(200)
            ->json();
        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testBalancePrefixOperatorFilters()
    {
        $this->client->balance = 5000;
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        foreach (['balance=gt:1000', 'balance=lt:9000', 'balance=eq:5000', 'balance=gte:5000'] as $q) {
            $arr = $this->withHeaders($this->headers())
                ->get('/api/v1/clients?' . $q)
                ->assertStatus(200)
                ->json();
            $this->assertContains($hash, $this->ids($arr), "expected match for ?$q");
        }

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?balance=gt:9000')
            ->assertStatus(200)
            ->json();
        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testMalformedOperatorIsSafeNoOp()
    {
        // Documents the framework's silent-skip contract: an unparseable
        // value returns the UNFILTERED set (no 422, no exception). If a
        // future strict mode changes this, this test is the guard.
        $hash = $this->encodePrimaryKey($this->client->id);

        $arr = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?created_at=garbage:xxx')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($arr));
    }

    public function testInvoiceDateComparator()
    {
        $this->invoice->date = '2015-06-15';
        $this->invoice->saveQuietly();
        $hash = $this->encodePrimaryKey($this->invoice->id);

        $match = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?date=lte:2015-06-15')
            ->assertStatus(200)
            ->json();
        $this->assertContains($hash, $this->ids($match));

        $miss = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?date=gt:2015-06-15')
            ->assertStatus(200)
            ->json();
        $this->assertNotContains($hash, $this->ids($miss));
    }

    public function testInvoiceDueDateComparatorIsSafeOnOpPrefix()
    {
        // Regression guard: due_date() previously had no try/catch, so an
        // `op:value` wire 500'd. It must now parse the prefix (or no-op).
        $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date=gte:2026-01-01')
            ->assertStatus(200);
        $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?due_date=garbage:xxx')
            ->assertStatus(200);
    }

    public function testQuoteAndCreditDateComparator()
    {
        // Quote/Credit previously had no date() method (inherited base,
        // which has none) — the param was silently ignored. Now applied.
        $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?date=gte:2999-01-01')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
        $this->withHeaders($this->headers())
            ->get('/api/v1/credits?date=gte:2999-01-01')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
