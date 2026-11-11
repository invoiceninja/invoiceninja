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

use App\Filters\PurchaseOrderFilters;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Coverage for App\Filters\PurchaseOrderFilters::client_status().
 */
class PurchaseOrderFilterTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    /** @var list<string> */
    private const STATUS_FILTERS = ['draft', 'sent', 'accepted', 'cancelled'];

    /** @var array<string, array{id: int, hashed_id: string, status_id: int, due_date: ?string}> */
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
            'draft_null_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_DRAFT, null),
            'draft_future_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_DRAFT, $tomorrow),
            'draft_past_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_DRAFT, $yesterday),
            'sent_null_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_SENT, null),
            'sent_future_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_SENT, $tomorrow),
            'sent_today_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_SENT, $today),
            'sent_past_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_SENT, $yesterday),
            'accepted' => $this->createPurchaseOrder(PurchaseOrder::STATUS_ACCEPTED, null),
            'cancelled' => $this->createPurchaseOrder(PurchaseOrder::STATUS_CANCELLED, $tomorrow),
            'received' => $this->createPurchaseOrder(PurchaseOrder::STATUS_RECEIVED, $tomorrow),
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
     * @return array{id: int, hashed_id: string, status_id: int, due_date: ?string}
     */
    private function createPurchaseOrder(int $statusId, ?string $dueDate): array
    {
        $purchase_order = PurchaseOrder::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'status_id' => $statusId,
            'number' => 'po-filter-' . $statusId . '-' . ($dueDate ?? 'null') . '-' . uniqid(),
            'due_date' => $dueDate,
        ]);

        return [
            'id' => $purchase_order->id,
            'hashed_id' => $purchase_order->hashed_id,
            'status_id' => $statusId,
            'due_date' => $dueDate,
        ];
    }

    /**
     * @param list<string> $filters
     * @return list<int>
     */
    private function filteredIds(array $filters): array
    {
        $value = implode(',', $filters);

        return PurchaseOrder::query()
            ->filter(new PurchaseOrderFilters(Request::create('/', 'GET', [
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
     * @param array{status_id: int, due_date: ?string} $record
     */
    private function purchaseOrderMatches(array $filters, array $record): bool
    {
        if ($filters === [] || in_array('all', $filters, true)) {
            return true;
        }

        if (in_array('draft', $filters, true) && $record['status_id'] === PurchaseOrder::STATUS_DRAFT) {
            return true;
        }

        if (in_array('sent', $filters, true) && $record['status_id'] === PurchaseOrder::STATUS_SENT) {
            return true;
        }

        if (in_array('accepted', $filters, true) && $record['status_id'] === PurchaseOrder::STATUS_ACCEPTED) {
            return true;
        }

        if (in_array('cancelled', $filters, true) && $record['status_id'] === PurchaseOrder::STATUS_CANCELLED) {
            return true;
        }

        return false;
    }

    public function testEmptyAndAllClientStatusIncludeEveryFixture(): void
    {
        foreach (['', 'all', 'all,draft'] as $client_status) {
            $response = $this->withHeaders($this->headers())
                ->get('/api/v1/purchase_orders?' . http_build_query([
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
                if ($this->purchaseOrderMatches($filters, $fixture)) {
                    $this->assertContains($fixture['id'], $ids, "Expected {$name} for client_status={$label}");
                } else {
                    $this->assertNotContains($fixture['id'], $ids, "Did not expect {$name} for client_status={$label}");
                }
            }
        }
    }

    public function testDraftSentAcceptedCancelledIncludesDraftsRegardlessOfParamOrder(): void
    {
        foreach (['draft,sent,accepted,cancelled', 'sent,cancelled,accepted,draft'] as $client_status) {
            $response = $this->withHeaders($this->headers())
                ->get('/api/v1/purchase_orders?' . http_build_query([
                    'client_status' => $client_status,
                    'per_page' => 500,
                ]))
                ->assertStatus(200);

            $ids = array_column($response->json('data'), 'id');

            $this->assertContains($this->fixtures['draft_null_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['draft_future_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['draft_past_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_null_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_future_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_today_due']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['accepted']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['cancelled']['hashed_id'], $ids);
            $this->assertContains($this->fixtures['sent_past_due']['hashed_id'], $ids);
            $this->assertNotContains($this->fixtures['received']['hashed_id'], $ids);
        }
    }

    public function testSentClientStatusMatchesAnyDueDate(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/purchase_orders?client_status=sent&per_page=500')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($this->fixtures['sent_null_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_future_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_today_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_past_due']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['draft_future_due']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['accepted']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['cancelled']['hashed_id'], $ids);
    }

    public function testDraftPlusSentDoesNotRequireBothClauses(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/purchase_orders?client_status=draft,sent&per_page=500')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($this->fixtures['draft_null_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_null_due']['hashed_id'], $ids);
        $this->assertContains($this->fixtures['sent_past_due']['hashed_id'], $ids);
        $this->assertNotContains($this->fixtures['accepted']['hashed_id'], $ids);
    }
}
