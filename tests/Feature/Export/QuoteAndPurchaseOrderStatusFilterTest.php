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

namespace Tests\Feature\Export;

use App\Export\CSV\BaseExport;
use App\Export\CSV\PurchaseOrderExport;
use App\Export\CSV\QuoteExport;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class ExportStatusFilterHarness extends BaseExport
{
    public function quoteStatus(Builder $query, string $status): Builder
    {
        return $this->addQuoteStatusFilter($query, $status);
    }

    public function purchaseOrderStatus(Builder $query, string $status): Builder
    {
        return $this->addPurchaseOrderStatusFilter($query, $status);
    }
}

/**
 * Coverage for BaseExport quote / purchase order status filters.
 */
class QuoteAndPurchaseOrderStatusFilterTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    /** @var list<string> */
    private const QUOTE_STATUS_FILTERS = ['draft', 'sent', 'approved', 'cancelled', 'rejected', 'expired', 'upcoming', 'converted'];

    /** @var list<string> */
    private const PO_STATUS_FILTERS = ['draft', 'sent', 'accepted', 'cancelled'];

    /** @var array<string, array{id: int, status_id: int, due_date: ?string, invoice_id: ?int}> */
    private array $quotes = [];

    /** @var array<string, array{id: int, status_id: int, due_date: ?string}> */
    private array $purchaseOrders = [];

    private ExportStatusFilterHarness $filters;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->filters = new ExportStatusFilterHarness();

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->quotes = [
            'draft' => $this->createQuote(Quote::STATUS_DRAFT, null),
            'draft_future_due' => $this->createQuote(Quote::STATUS_DRAFT, $tomorrow),
            'draft_converted' => $this->createQuote(Quote::STATUS_DRAFT, $tomorrow, $this->invoice->id),
            'approved' => $this->createQuote(Quote::STATUS_APPROVED, $tomorrow),
            'cancelled' => $this->createQuote(Quote::STATUS_CANCELLED, $yesterday),
            'rejected' => $this->createQuote(Quote::STATUS_REJECTED, $tomorrow),
            'sent_null_due' => $this->createQuote(Quote::STATUS_SENT, null),
            'sent_future_due' => $this->createQuote(Quote::STATUS_SENT, $tomorrow),
            'sent_today_due' => $this->createQuote(Quote::STATUS_SENT, $today),
            'sent_past_due' => $this->createQuote(Quote::STATUS_SENT, $yesterday),
            'sent_converted_future' => $this->createQuote(Quote::STATUS_SENT, $tomorrow, $this->invoice->id),
        ];

        $this->purchaseOrders = [
            'draft_null_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_DRAFT, null),
            'draft_future_due' => $this->createPurchaseOrder(PurchaseOrder::STATUS_DRAFT, $tomorrow),
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
     * @return array{id: int, status_id: int, due_date: ?string, invoice_id: ?int}
     */
    private function createQuote(int $statusId, ?string $dueDate, ?int $invoiceId = null): array
    {
        $quote = Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $statusId,
            'number' => 'export-quote-status-' . $statusId . '-' . ($dueDate ?? 'null') . '-' . uniqid(),
            'due_date' => $dueDate,
            'invoice_id' => $invoiceId,
        ]);

        return [
            'id' => $quote->id,
            'status_id' => $statusId,
            'due_date' => $dueDate,
            'invoice_id' => $invoiceId,
        ];
    }

    /**
     * @return array{id: int, status_id: int, due_date: ?string}
     */
    private function createPurchaseOrder(int $statusId, ?string $dueDate): array
    {
        $purchase_order = PurchaseOrder::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'status_id' => $statusId,
            'number' => 'export-po-status-' . $statusId . '-' . ($dueDate ?? 'null') . '-' . uniqid(),
            'due_date' => $dueDate,
        ]);

        return [
            'id' => $purchase_order->id,
            'status_id' => $statusId,
            'due_date' => $dueDate,
        ];
    }

    /**
     * @param list<string> $items
     * @return list<list<string>>
     */
    private function combinations(array $items): array
    {
        $count = count($items);
        $combinations = [];

        for ($mask = 0; $mask < (1 << $count); $mask++) {
            $combination = [];

            for ($i = 0; $i < $count; $i++) {
                if ($mask & (1 << $i)) {
                    $combination[] = $items[$i];
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

    /**
     * @return array<string, mixed>
     */
    private function exportInput(string $status): array
    {
        return [
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'status' => $status,
        ];
    }

    public function testEveryQuoteExportStatusCombination(): void
    {
        foreach ($this->combinations(self::QUOTE_STATUS_FILTERS) as $filters) {
            $ids = $this->filters
                ->quoteStatus(Quote::query()->where('company_id', $this->company->id), implode(',', $filters))
                ->pluck('id')
                ->all();

            $label = $filters === [] ? '(empty)' : implode(',', $filters);

            foreach ($this->quotes as $name => $fixture) {
                if ($this->quoteMatches($filters, $fixture)) {
                    $this->assertContains($fixture['id'], $ids, "Expected {$name} for quote export status={$label}");
                } else {
                    $this->assertNotContains($fixture['id'], $ids, "Did not expect {$name} for quote export status={$label}");
                }
            }
        }
    }

    public function testEveryPurchaseOrderExportStatusCombination(): void
    {
        foreach ($this->combinations(self::PO_STATUS_FILTERS) as $filters) {
            $ids = $this->filters
                ->purchaseOrderStatus(PurchaseOrder::query()->where('company_id', $this->company->id), implode(',', $filters))
                ->pluck('id')
                ->all();

            $label = $filters === [] ? '(empty)' : implode(',', $filters);

            foreach ($this->purchaseOrders as $name => $fixture) {
                if ($this->purchaseOrderMatches($filters, $fixture)) {
                    $this->assertContains($fixture['id'], $ids, "Expected {$name} for PO export status={$label}");
                } else {
                    $this->assertNotContains($fixture['id'], $ids, "Did not expect {$name} for PO export status={$label}");
                }
            }
        }
    }

    public function testQuoteExportInitIncludesDraftsWhenAllNamedStatusesAreSet(): void
    {
        $status = 'draft,sent,approved,cancelled,rejected,expired,upcoming,converted';

        $ids = (new QuoteExport($this->company, $this->exportInput($status)))
            ->init()
            ->pluck('id')
            ->all();

        $this->assertContains($this->quotes['draft']['id'], $ids);
        $this->assertContains($this->quotes['draft_future_due']['id'], $ids);
        $this->assertContains($this->quotes['approved']['id'], $ids);
        $this->assertContains($this->quotes['cancelled']['id'], $ids);
        $this->assertContains($this->quotes['rejected']['id'], $ids);
        $this->assertContains($this->quotes['sent_null_due']['id'], $ids);
        $this->assertContains($this->quotes['sent_past_due']['id'], $ids);
    }

    public function testPurchaseOrderExportInitIncludesDraftsWhenAllNamedStatusesAreSet(): void
    {
        $status = 'draft,sent,accepted,cancelled';

        $ids = (new PurchaseOrderExport($this->company, $this->exportInput($status)))
            ->init()
            ->pluck('id')
            ->all();

        $this->assertContains($this->purchaseOrders['draft_null_due']['id'], $ids);
        $this->assertContains($this->purchaseOrders['draft_future_due']['id'], $ids);
        $this->assertContains($this->purchaseOrders['sent_null_due']['id'], $ids);
        $this->assertContains($this->purchaseOrders['accepted']['id'], $ids);
        $this->assertContains($this->purchaseOrders['cancelled']['id'], $ids);
        $this->assertContains($this->purchaseOrders['sent_past_due']['id'], $ids);
        $this->assertNotContains($this->purchaseOrders['received']['id'], $ids);
    }

    public function testQuoteExportCancelledReturnsOnlyCancelledQuotes(): void
    {
        $ids = (new QuoteExport($this->company, $this->exportInput('cancelled')))
            ->init()
            ->pluck('id')
            ->all();

        $this->assertContains($this->quotes['cancelled']['id'], $ids);
        $this->assertNotContains($this->quotes['draft']['id'], $ids);
        $this->assertNotContains($this->quotes['approved']['id'], $ids);
        $this->assertNotContains($this->quotes['sent_null_due']['id'], $ids);
        $this->assertNotContains($this->quotes['sent_past_due']['id'], $ids);
        $this->assertNotContains($this->quotes['draft_converted']['id'], $ids);
        $this->assertNotContains($this->quotes['rejected']['id'], $ids);
    }

    public function testQuoteExportRejectedReturnsOnlyRejectedQuotes(): void
    {
        $ids = (new QuoteExport($this->company, $this->exportInput('rejected')))
            ->init()
            ->pluck('id')
            ->all();

        $this->assertContains($this->quotes['rejected']['id'], $ids);
        $this->assertNotContains($this->quotes['cancelled']['id'], $ids);
        $this->assertNotContains($this->quotes['draft']['id'], $ids);
        $this->assertNotContains($this->quotes['approved']['id'], $ids);
        $this->assertNotContains($this->quotes['sent_null_due']['id'], $ids);
    }

    public function testQuoteExportSentDoesNotMatchDraftsWithFutureDueDates(): void
    {
        $ids = (new QuoteExport($this->company, $this->exportInput('sent')))
            ->init()
            ->pluck('id')
            ->all();

        $this->assertContains($this->quotes['sent_null_due']['id'], $ids);
        $this->assertContains($this->quotes['sent_future_due']['id'], $ids);
        $this->assertNotContains($this->quotes['sent_past_due']['id'], $ids);
        $this->assertNotContains($this->quotes['draft']['id'], $ids);
        $this->assertNotContains($this->quotes['draft_future_due']['id'], $ids);
    }

    public function testPurchaseOrderExportDraftPlusSentDoesNotRequireBothClauses(): void
    {
        $ids = (new PurchaseOrderExport($this->company, $this->exportInput('draft,sent')))
            ->init()
            ->pluck('id')
            ->all();

        $this->assertContains($this->purchaseOrders['draft_null_due']['id'], $ids);
        $this->assertContains($this->purchaseOrders['sent_null_due']['id'], $ids);
        $this->assertContains($this->purchaseOrders['sent_past_due']['id'], $ids);
        $this->assertNotContains($this->purchaseOrders['accepted']['id'], $ids);
    }
}
