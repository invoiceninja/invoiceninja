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

use App\Filters\QuoteFilters;
use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Coverage for App\Filters\QuoteFilters::client_status().
 */
class QuoteFilterTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    private Quote $approvedQuote;

    private Quote $draftQuote;

    private Quote $rejectedQuote;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->draftQuote = $this->createQuoteForFilter(Quote::STATUS_DRAFT, 'draft');
        $this->approvedQuote = $this->createQuoteForFilter(Quote::STATUS_APPROVED, 'approved');
        $this->rejectedQuote = $this->createQuoteForFilter(Quote::STATUS_REJECTED, 'rejected');
    }

    private function headers(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /** @return array<int, string> */
    private function quoteIdsFromResponse(array $payload): array
    {
        return array_column($payload['data'], 'id');
    }

    private function createQuoteForFilter(int $statusId, string $label): Quote
    {
        return Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $statusId,
            'number' => 'quote-filter-' . $label . '-' . uniqid(),
            'due_date' => now()->addMonth(),
        ]);
    }

    public function testClientStatusApprovedReturnsOnlyApprovedQuotes(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=approved&per_page=500')
            ->assertStatus(200);

        $ids = $this->quoteIdsFromResponse($response->json());

        $this->assertContains($this->approvedQuote->hashed_id, $ids);
        $this->assertNotContains($this->quote->hashed_id, $ids);
        $this->assertNotContains($this->draftQuote->hashed_id, $ids);
        $this->assertNotContains($this->rejectedQuote->hashed_id, $ids);
    }

    public function testClientStatusApprovedDoesNotReturnAllStatuses(): void
    {
        $allResponse = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?per_page=500')
            ->assertStatus(200);

        $approvedResponse = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=approved&per_page=500')
            ->assertStatus(200);

        $allIds = $this->quoteIdsFromResponse($allResponse->json());
        $approvedIds = $this->quoteIdsFromResponse($approvedResponse->json());

        $this->assertGreaterThan(count($approvedIds), count($allIds));
        $this->assertContains($this->quote->hashed_id, $allIds);
        $this->assertNotContains($this->quote->hashed_id, $approvedIds);
    }

    public function testClientStatusSentAndApprovedReturnsBothStatuses(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=sent,approved&per_page=500')
            ->assertStatus(200);

        $ids = $this->quoteIdsFromResponse($response->json());

        $this->assertContains($this->quote->hashed_id, $ids);
        $this->assertContains($this->approvedQuote->hashed_id, $ids);
        $this->assertNotContains($this->draftQuote->hashed_id, $ids);
        $this->assertNotContains($this->rejectedQuote->hashed_id, $ids);
    }

    public function testQuoteFiltersClientStatusApprovedSqlRestrictsToApprovedStatus(): void
    {
        $filters = new QuoteFilters(Request::create('/', 'GET', [
            'client_status' => 'approved',
        ]));

        $builder = $filters->apply(Quote::query());

        $this->assertStringContainsString('status_id', $builder->toSql());
        $this->assertContains(Quote::STATUS_APPROVED, $builder->getBindings());

        $ids = $builder->pluck('id')->all();

        $this->assertContains($this->approvedQuote->id, $ids);
        $this->assertNotContains($this->quote->id, $ids);
        $this->assertNotContains($this->draftQuote->id, $ids);
        $this->assertNotContains($this->rejectedQuote->id, $ids);
    }
}
