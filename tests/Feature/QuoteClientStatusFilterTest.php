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
class QuoteClientStatusFilterTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    /** @var list<string> */
    private const STATUS_FILTERS = ['draft', 'sent', 'approved', 'cancelled', 'rejected', 'expired', 'upcoming', 'converted'];

    /** @var array<string, array{id: int, hashed_id: string, status_id: int, due_date: ?string, invoice_id: ?int}> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->fixtures = [
            'draft' => $this->createQuote(Quote::STATUS_DRAFT, null),
            'draft_converted' => $this->createQuote(Quote::STATUS_DRAFT, $tomorrow, $this->invoice->id),
            'approved' => $this->createQuote(Quote::STATUS_APPROVED, $tomorrow),
            'cancelled' => $this->createQuote(Quote::STATUS_CANCELLED, $yesterday),
            'rejected' => $this->createQuote(Quote::STATUS_REJECTED, $tomorrow),
            'sent_null_due' => $this->createQuote(Quote::STATUS_SENT, null),
            'sent_future_due' => $this->createQuote(Quote::STATUS_SENT, $tomorrow),
            'sent_today_due' => $this->createQuote(Quote::STATUS_SENT, $today),
            'sent_past_due' => $this->createQuote(Quote::STATUS_SENT, $yesterday),
            'sent_converted_future' => $this->createQuote(Quote::STATUS_SENT, $tomorrow, $this->invoice->id),
            'converted_status' => $this->createQuote(Quote::STATUS_CONVERTED, $tomorrow),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /**
     * @return array{id: int, hashed_id: string, status_id: int, due_date: ?string, invoice_id: ?int}
     */
    private function createQuote(int $statusId, ?string $dueDate, ?int $invoiceId = null): array
    {
        $quote = Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $statusId,
            'number' => 'quote-status-filter-' . $statusId . '-' . ($dueDate ?? 'null') . '-' . uniqid(),
            'due_date' => $dueDate,
            'invoice_id' => $invoiceId,
        ]);

        return [
            'id' => $quote->id,
            'hashed_id' => $quote->hashed_id,
            'status_id' => $statusId,
            'due_date' => $dueDate,
            'invoice_id' => $invoiceId,
        ];
    }

    /**
     * @param list<string> $filters
     * @return list<int>
     */
    private function filteredIds(array $filters): array
    {
        $value = implode(',', $filters);

        return Quote::query()
            ->filter(new QuoteFilters(Request::create('/', 'GET', [
                'client_status' => $value,
            ])))
            ->pluck('id')
            ->all();
    }

    /**
     * @return list<list<string>>
     */
    private function statusCombinations(): array
    {
        $statuses = self::STATUS_FILTERS;
        $count = count($statuses);
        $combinations = [];

        for ($mask = 0; $mask < (1 << $count); $mask++) {
            $combination = [];

            for ($i = 0; $i < $count; $i++) {
                if ($mask & (1 << $i)) {
                    $combination[] = $statuses[$i];
                }
            }

            $combinations[] = $combination;
        }

        return $combinations;
    }

    /**
     * @param list<string> $filters
     * @param array{status_id: int, due_date: ?string, invoice_id: ?int} $record
     */
    private function quoteMatches(array $filters, array $record): bool
    {
        if ($filters === [] || in_array('all', $filters, true)) {
            return true;
        }

        $today = now()->toDateString();

        if (in_array('draft', $filters, true) && $record['status_id'] === Quote::STATUS_DRAFT) {
            return true;
        }

        if (in_array('approved', $filters, true) && $record['status_id'] === Quote::STATUS_APPROVED) {
            return true;
        }

        if (in_array('cancelled', $filters, true) && $record['status_id'] === Quote::STATUS_CANCELLED) {
            return true;
        }

        if (in_array('rejected', $filters, true) && $record['status_id'] === Quote::STATUS_REJECTED) {
            return true;
        }

        if (
            in_array('sent', $filters, true)
            && $record['status_id'] === Quote::STATUS_SENT
            && ($record['due_date'] === null || $record['due_date'] >= $today)
        ) {
            return true;
        }

        if (
            in_array('expired', $filters, true)
            && $record['status_id'] === Quote::STATUS_SENT
            && $record['due_date'] !== null
            && $record['due_date'] <= $today
        ) {
            return true;
        }

        if (
            in_array('upcoming', $filters, true)
            && $record['status_id'] === Quote::STATUS_SENT
            && $record['due_date'] !== null
            && $record['due_date'] >= $today
        ) {
            return true;
        }

        if (in_array('converted', $filters, true) && $record['invoice_id'] !== null) {
            return true;
        }

        return false;
    }

    public function testEmptyAndAllClientStatusIncludeEveryFixture(): void
    {
        foreach (['', 'all', 'all,draft'] as $client_status) {
            $response = $this->withHeaders($this->headers())
                ->get('/api/v1/quotes?' . http_build_query([
                    'client_status' => $client_status,
                    'per_page' => 500,
                ]))
                ->assertStatus(200);

            $ids = array_column($response->json('data'), 'id');

            foreach ($this->fixtures as $label => $fixture) {
                $this->assertContains(
                    $fixture['hashed_id'],
                    $ids,
                    "Expected {$label} when client_status={$client_status}"
                );
            }
        }
    }

    public function testEveryNamedClientStatusCombination(): void
    {
        foreach ($this->statusCombinations() as $filters) {
            $ids = $this->filteredIds($filters);
            $label = $filters === [] ? '(empty)' : implode(',', $filters);

            foreach ($this->fixtures as $name => $fixture) {
                if ($this->quoteMatches($filters, $fixture)) {
                    $this->assertContains($fixture['id'], $ids, "Expected {$name} for client_status={$label}");
                } else {
                    $this->assertNotContains($fixture['id'], $ids, "Did not expect {$name} for client_status={$label}");
                }
            }
        }
    }

    public function testAllNamedStatusesIncludeDraftsRegardlessOfParamOrder(): void
    {
        $all = 'draft,sent,approved,cancelled,rejected,expired,upcoming,converted';
        $reversed = 'converted,upcoming,expired,rejected,cancelled,approved,sent,draft';

        foreach ([$all, $reversed] as $client_status) {
            $response = $this->withHeaders($this->headers())
                ->get('/api/v1/quotes?' . http_build_query([
                    'client_status' => $client_status,
                    'per_page' => 500,
                ]))
                ->assertStatus(200);

            $ids = array_column($response->json('data'), 'id');

            $this->assertContains($this->fixtures['draft']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['draft_converted']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['approved']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['cancelled']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['rejected']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_null_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_future_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_today_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_past_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_converted_future']['hashed_id'], $ids);
            $this->assertNotContains($this->fixtures['converted_status']['hashed_id'], $ids);
        }
    }

    public function testSentClientStatusScopesStatusAndDueDate(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=sent&per_page=500')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($this->fixtures['sent_null_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_future_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_today_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_converted_future']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['sent_past_due']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['draft']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['approved']['hashed_id'], $ids);
    }

    public function testExpiredAndUpcomingAreDueDateScopedSentQuotes(): void
    {
        $expired = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=expired&per_page=500')
            ->assertStatus(200);

        $upcoming = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=upcoming&per_page=500')
            ->assertStatus(200);

        $expiredIds = array_column($expired->json('data'), 'id');
        $upcomingIds = array_column($upcoming->json('data'), 'id');

        $this->assertContains($this->fixtures['sent_past_due']['hashed_id'], $expiredIds);
        $this->assertContains($this->fixtures['sent_today_due']['hashed_id'], $expiredIds);
        $this->assertNotContains($this->fixtures['sent_future_due']['hashed_id'], $expiredIds);
        $this->assertNotContains($this->fixtures['sent_null_due']['hashed_id'], $expiredIds);
        $this->assertNotContains($this->fixtures['draft']['hashed_id'], $expiredIds);

        $this->assertContains($this->fixtures['sent_future_due']['hashed_id'], $upcomingIds);
        $this->assertContains($this->fixtures['sent_today_due']['hashed_id'], $upcomingIds);
        $this->assertNotContains($this->fixtures['sent_past_due']['hashed_id'], $upcomingIds);
        $this->assertNotContains($this->fixtures['sent_null_due']['hashed_id'], $upcomingIds);
        $this->assertNotContains($this->fixtures['draft']['hashed_id'], $upcomingIds);
    }

    public function testConvertedMatchesInvoiceIdNotConvertedStatus(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=converted&per_page=500')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($this->fixtures['draft_converted']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_converted_future']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['converted_status']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['draft']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['sent_future_due']['hashed_id'], $ids);
    }

    public function testDraftPlusSentDoesNotRequireBothClauses(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/quotes?client_status=draft,sent&per_page=500')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($this->fixtures['draft']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['draft_converted']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_null_due']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['sent_past_due']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['approved']['hashed_id'], $ids);
    }
}
