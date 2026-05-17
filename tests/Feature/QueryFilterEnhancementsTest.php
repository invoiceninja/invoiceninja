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
 *  - number / id_number LIKE
 *  - updated_between
 *  - meta.warnings unknown-filter + deprecation envelope
 *  - opt-in strict mode (422)
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

    public function testUnknownFilterParamIsWarnedNotFiltered()
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?bogus_param=1')
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertContains('bogus_param', $arr['meta']['warnings']['unknown_filters']);
        // Non-breaking: the unknown param did not filter anything out.
        $this->assertNotEmpty($arr['data']);
    }

    public function testReservedFrameworkParamsDoNotWarn()
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?per_page=20&t=123456&_=1736900000&clear_cache=true&include=')
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertArrayNotHasKey('warnings', $arr['meta'] ?? []);
    }

    public function testStrictParamIsInertAndOnlyTheRealUnknownWarns()
    {
        // strict was removed; ?strict is reserved (inert) so only bogus_param warns.
        $arr = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?bogus_param=1&strict=true')
            ->assertStatus(200)
            ->json();

        $this->assertSame(['bogus_param'], $arr['meta']['warnings']['unknown_filters']);
    }

    public function testLegacyDateRangeOnColumnlessEntityEmitsNoDeprecation()
    {
        // clients has no `date` column: a 2-part date_range no-ops, so it must
        // NOT surface a spurious meta.warnings.deprecations.
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

    public function testClientNumberIsPrefixMatched()
    {
        $this->client->number = 'PREFIX-000123';
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        $arr = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?number=PREFIX-000')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($arr));
    }

    public function testClientIdNumberIsSubstringMatched()
    {
        $this->client->id_number = 'ID-MIDDLE-XYZ';
        $this->client->saveQuietly();

        $hash = $this->encodePrimaryKey($this->client->id);

        $arr = $this->withHeaders($this->headers())
            ->get('/api/v1/clients?id_number=MIDDLE')
            ->assertStatus(200)
            ->json();

        $this->assertContains($hash, $this->ids($arr));
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

    public function testDateRangeLegacyTwoPartStillFiltersAndEmitsDeprecation()
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
        // ... and the legacy shape is announced via the deprecation channel.
        $this->assertNotEmpty($match['meta']['warnings']['deprecations']);

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
}
