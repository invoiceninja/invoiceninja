<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Export;

use App\Export\CSV\BaseExport;
use App\Export\CSV\InvoiceExport;
use App\Export\CSV\ProductSalesExport;
use App\Factory\InvoiceItemFactory;
use App\Jobs\Entity\ZipEntity;
use App\Listeners\Invoice\InvoiceTransactionEventEntry;
use App\Models\Activity;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\TransactionEvent;
use App\Services\Report\EInvoiceReport;
use App\Services\Report\TaxPeriodReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use League\Csv\Reader;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

class StreamQueryPaginationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const ROW_COUNT = 503; // Cross the production 500-row boundary with tied dates.

    public static function exports(): array
    {
        return [
            'activities' => ['ActivityExport', 'activity', ['date', 'activity', 'address', 'notes']],
            'clients' => ['ClientExport', 'client', ['client.name']],
            'contacts' => ['ContactExport', 'contact', ['contact.email']],
            'credits' => ['CreditExport', 'credit', ['credit.number']],
            'documents' => ['DocumentExport', 'document', ['name']],
            'expenses' => ['ExpenseExport', 'expense', ['expense.number']],
            'invoices' => ['InvoiceExport', 'invoice', ['invoice.number', 'invoice.total_taxes']],
            'invoice items' => ['InvoiceItemExport', 'invoice', ['invoice.number', 'item.product_key']],
            'locations' => ['LocationExport', 'location', ['location.name']],
            'payments' => ['PaymentExport', 'payment', ['payment.number']],
            'products' => ['ProductExport', 'product', ['product.product_key']],
            'product sales' => ['ProductSalesExport', 'invoice', []],
            'purchase orders' => ['PurchaseOrderExport', 'purchase_order', ['purchase_order.number']],
            'purchase order items' => ['PurchaseOrderItemExport', 'purchase_order', ['purchase_order.number', 'item.product_key']],
            'quotes' => ['QuoteExport', 'quote', ['quote.number']],
            'quote items' => ['QuoteItemExport', 'quote', ['quote.number', 'item.product_key']],
            'recurring invoices' => ['RecurringInvoiceExport', 'recurring_invoice', ['recurring_invoice.number']],
            'recurring invoice items' => ['RecurringInvoiceItemExport', 'recurring_invoice', ['recurring_invoice.number', 'item.product_key']],
            'tasks' => ['TaskExport', 'task', ['task.number']],
            'vendors' => ['VendorExport', 'vendor', ['vendor.name']],
        ];
    }

    private function payload(array $keys): array
    {
        return [
            'date_range' => 'custom',
            'start_date' => '2020-01-01',
            'end_date' => '2020-01-31',
            'report_keys' => $keys,
            'include_deleted' => false,
            'client_id' => null,
            'product_key' => null,
            'status' => '',
            'document_email_attachment' => false,
            'is_income_billed' => true,
        ];
    }

    /** Exercise the actual query construction, pagination and row consumers, not a mock lazy(). */
    #[DataProvider('exports')]
    public function test_every_export_streams_each_record_once_in_date_then_id_order(string $name, string $fixture, array $keys): void
    {
        $this->makeTestData();
        // Keep the factory's random date outside the pagination fixture window.
        $this->recurring_invoice->updateQuietly(['date' => '2019-12-01']);

        $class = 'App\\Export\\CSV\\' . $name;
        $dateKey = (new $class($this->company, $this->payload($keys)))->date_key;
        [$model, $ids] = $this->seedPages($fixture, $dateKey);
        $expected = array_merge(array_slice($ids, 2), array_slice($ids, 0, 2));

        $methods = $class === ProductSalesExport::class ? ['run'] : ['run', 'returnJson'];
        foreach ($methods as $method) {
            $streams = [];
            $export = $this->observeStreams($class, $keys, $streams);
            $connection = $model->getConnection();
            $connection->flushQueryLog();
            $connection->enableQueryLog();
            try {
                $output = $export->$method();
                $queries = $connection->getQueryLog();
            } finally {
                $connection->disableQueryLog();
            }

            $expectedStreams = $class === InvoiceExport::class && $method === 'run' ? 2 : 1;
            $this->assertCount($expectedStreams, $streams, "$name::$method must exercise every stream, including tax discovery");
            foreach ($streams as $stream) {
                $this->assertSame($expected, $stream->ids, "$name::$method omitted, duplicated or reordered records");
                $this->assertSame($stream->sql, $stream->query->toSql(), 'Streaming must not mutate the caller query');
            }

            // MySQL may incidentally return tied dates in ID order without the fix.
            // Check every executed page has an explicit total order as well as checking results.
            $pages = array_values(array_filter($queries, fn ($q) =>
                str_contains($q['query'], 'from `' . $model->getTable() . '`')
                && str_contains($q['query'], 'limit 500 offset ')
            ));
            $this->assertCount(2 * $expectedStreams, $pages);
            foreach ($pages as $page) {
                $this->assertStringContainsString(
                    'order by `' . $dateKey . '` asc, `' . $model->getTable() . '`.`id` asc',
                    $page['query']
                );
            }

            if ($method === 'returnJson') {
                unset($output['columns']);
                $this->assertCount(self::ROW_COUNT, $output);
            } elseif ($class !== ProductSalesExport::class) {
                $reader = Reader::fromString($output);
                $reader->setHeaderOffset(0);
                $this->assertCount(self::ROW_COUNT, iterator_to_array($reader->getRecords()));
            } else {
                // Product Sales also appends a summary: verify all detail rows and their totals.
                $rows = iterator_to_array(Reader::fromString($output)->getRecords(), false);
                $header = array_shift($rows);
                $details = array_slice($rows, 0, self::ROW_COUNT);
                $this->assertCount(self::ROW_COUNT, $details);
                $productColumn = array_search(ctrans('texts.product_key'), $header, true);
                $totalColumn = array_search(ctrans('texts.line_total'), $header, true);
                $this->assertNotFalse($productColumn);
                $this->assertNotFalse($totalColumn);
                $this->assertSame(array_fill(0, self::ROW_COUNT, 'pagination-product'), array_column($details, $productColumn));
                $this->assertEquals(self::ROW_COUNT * 10, array_sum(array_column($details, $totalColumn)));
            }
        }
    }

    private function observeStreams(string $class, array $keys, array &$streams): BaseExport
    {
        $arguments = [$this->company, $this->payload($keys)];
        if ($class === TaxPeriodReport::class) {
            $arguments[] = true; // Initialization is exercised separately from report assembly.
        }
        $export = Mockery::mock($class, $arguments)
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $helpers = ['streamQuery' => BaseExport::class];
        if ($class === TaxPeriodReport::class) {
            $helpers['streamQueryById'] = TaxPeriodReport::class;
        }
        foreach ($helpers as $helper => $owner) {
            $export->shouldReceive($helper)->andReturnUsing(function (Builder $query) use ($export, &$streams, $helper, $owner) {
                $stream = (object) ['ids' => [], 'query' => $query, 'sql' => $query->toSql(), 'helper' => $helper];
                $streams[] = $stream;
                $result = (new ReflectionMethod($owner, $helper))->invoke($export, $query);

                return $result->map(function (Model $model) use ($stream) {
                    $stream->ids[] = $model->getKey();
                    return $model;
                });
            });
        }
        return $export;
    }

    /** Bulk insert valid model attributes without hundreds of unrelated model-event side effects. */
    private function seedPages(string $fixture, string $dateKey): array
    {
        $model = match ($fixture) {
            'document' => Document::factory()->make([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'documentable_type' => Invoice::class,
                'documentable_id' => $this->invoice->id,
                'hash' => 'pagination',
                'url' => 'pagination.pdf',
                'disk' => 'local',
                'type' => 'application/pdf',
                'size' => 1,
            ]),
            'location' => Location::factory()->make([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
            ]),
            'activity' => (new Activity())->forceFill([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'activity_type_id' => Activity::CREATE_CLIENT,
                'notes' => 'pagination',
            ]),
            default => $this->$fixture,
        };
        $attributes = $model->getAttributes();
        unset($attributes['id']);
        if (in_array($fixture, ['task', 'expense'], true)) {
            // These factories do not assign a number; keep exported fixture rows identifiable.
            $attributes['number'] = 'pagination';
        }
        if (array_key_exists('deleted_at', $attributes)) {
            $attributes['deleted_at'] = null;
        }
        if (array_key_exists('is_deleted', $attributes)) {
            $attributes['is_deleted'] = 0;
        }
        if (array_key_exists('line_items', $attributes)) {
            $item = InvoiceItemFactory::create();
            $item->product_key = 'pagination-product';
            $item->cost = 10;
            $item->quantity = 1;
            $item->line_total = 10;
            $item->gross_line_total = 10;
            $attributes['line_items'] = json_encode([$item]);
        }
        if ($model instanceof Invoice) {
            $attributes['status_id'] = Invoice::STATUS_SENT;
            $attributes['amount'] = 10;
            $attributes['balance'] = 10;
            $attributes['paid_to_date'] = 0;
            $attributes['discount'] = 0;
            $attributes['total_taxes'] = 0;
            foreach ([1, 2, 3] as $tax) {
                $attributes['tax_name' . $tax] = '';
                $attributes['tax_rate' . $tax] = 0;
            }
        }

        $rows = [];
        for ($i = 0; $i < self::ROW_COUNT; $i++) {
            $row = $attributes;
            $row[$dateKey] = $i < 2 ? '2020-01-20' : '2020-01-10';
            foreach (['number', 'email', 'product_key', 'hash'] as $unique) {
                if (array_key_exists($unique, $row)) {
                    $row[$unique] = "pagination-$i" . ($unique === 'email' ? '@example.test' : '');
                }
            }
            $rows[] = $row;
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            $model->getConnection()->table($model->getTable())->insert($chunk);
        }
        $ids = $model->newQueryWithoutScopes()->where('company_id', $this->company->id)
            ->whereBetween($dateKey, ['2020-01-01', '2020-01-31'])->orderBy('id')->pluck('id')->all();
        $this->assertCount(self::ROW_COUNT, $ids);
        return [$model, $ids];
    }

    public function test_document_attachment_caller_includes_documents_from_every_page_once(): void
    {
        $this->makeTestData();
        [$model, $ids] = $this->seedPages('invoice', 'date');
        $documents = [];
        foreach ($ids as $id) {
            $documents[] = [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'documentable_type' => $model->getMorphClass(),
                'documentable_id' => $id,
                'name' => "invoice-$id.pdf",
                'hash' => "pagination-$id",
                'url' => 'pagination.pdf',
                'disk' => 'local',
                'type' => 'application/pdf',
                'size' => 1,
            ];
        }
        Document::insert($documents);
        $documentIds = Document::where('company_id', $this->company->id)
            ->whereIn('documentable_id', $ids)->orderBy('id')->pluck('id')->all();
        Bus::fake([ZipEntity::class]);
        $streams = [];
        $export = $this->observeStreams(InvoiceExport::class, ['invoice.number'], $streams);
        $export->queueDocuments(Invoice::whereIn('id', $ids)->orderBy('date'));
        $this->assertSame(array_merge(array_slice($ids, 2), array_slice($ids, 0, 2)), $streams[0]->ids);
        Bus::assertDispatchedTimes(ZipEntity::class, 1);
        Bus::assertDispatched(ZipEntity::class, function ($job) use ($documentIds) {
            $this->assertSame(array_merge(array_slice($documentIds, 2), array_slice($documentIds, 0, 2)), $job->entity_ids);
            return $job->entity_class === Document::class;
        });
    }

    public function test_shared_stream_preserves_existing_sorts_filters_and_reusable_queries(): void
    {
        $this->makeTestData();
        [, $ids] = $this->seedPages('invoice', 'date');
        $export = new InvoiceExport($this->company, $this->payload(['invoice.number']));
        $stream = new ReflectionMethod(BaseExport::class, 'streamQuery');
        $base = Invoice::whereIn('invoices.id', $ids);
        $early = array_slice($ids, 2);
        $late = array_slice($ids, 0, 2);

        $cases = [
            'no explicit sort (all dates)' => [clone $base, $ids],
            'descending date' => [(clone $base)->orderByDesc('date'), array_merge($late, $early)],
            'existing descending tie breaker' => [
                (clone $base)->orderBy('date')->orderByDesc('invoices.id'),
                array_merge(array_reverse($early), array_reverse($late)),
            ],
            'join with another id column' => [
                (clone $base)->join('clients', 'clients.id', '=', 'invoices.client_id')
                    ->select('invoices.*')->orderBy('invoices.date'),
                array_merge($early, $late),
            ],
            'empty result' => [(clone $base)->whereRaw('1 = 0')->orderBy('date'), []],
        ];

        foreach ($cases as $label => [$query, $expected]) {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            $query->getConnection()->flushQueryLog();
            $query->getConnection()->enableQueryLog();
            $rows = $stream->invoke($export, $query);
            $this->assertSame([], $query->getConnection()->getQueryLog(), 'Creating the stream must stay lazy');
            $query->getConnection()->disableQueryLog();

            $this->assertSame($expected, $rows->pluck('id')->all(), $label);
            // Both the collection and its originating query must remain reusable.
            $this->assertSame($expected, $rows->pluck('id')->all(), $label);
            $this->assertSame($expected, $stream->invoke($export, $query)->pluck('id')->all(), $label);
            $this->assertSame($sql, $query->toSql(), $label);
            $this->assertSame($bindings, $query->getBindings(), $label);
        }
    }

    public function test_einvoice_report_streams_both_invoices_and_received_expenses(): void
    {
        $this->makeTestData();
        [, $invoiceIds] = $this->seedPages('invoice', 'date');
        [, $expenseIds] = $this->seedPages('expense', 'date');
        Activity::insert(array_map(fn ($id) => [
            'company_id' => $this->company->id,
            'activity_type_id' => 148,
            'expense_id' => $id,
            'created_at' => '2020-01-15',
        ], $expenseIds));
        $streams = [];
        $export = $this->observeStreams(EInvoiceReport::class, [], $streams);
        $csv = $export->run();
        $this->assertCount(2, $streams);
        $this->assertSame(array_merge(array_slice($invoiceIds, 2), array_slice($invoiceIds, 0, 2)), $streams[0]->ids);
        $this->assertSame($expenseIds, $streams[1]->ids);
        $rows = iterator_to_array(Reader::fromString($csv)->getRecords(), false);
        $this->assertCount(self::ROW_COUNT * 2, array_filter($rows, fn ($row) => str_starts_with($row[0] ?? '', 'pagination-') || str_starts_with($row[6] ?? '', 'pagination-')));
    }

    private function seedTransactionEvents(array $invoiceIds, ?string $missingField = null, bool $cancelled = false): array
    {
        $invoice = Invoice::findOrFail($invoiceIds[0]);
        (new InvoiceTransactionEventEntry())->run($invoice, '2020-01-15');
        $template = $invoice->transaction_events()->where('period', '2020-01-15')->firstOrFail();
        $attributes = $template->getAttributes();
        unset($attributes['id']);
        $metadata = json_decode($attributes['metadata'], true);
        if ($missingField) {
            unset($metadata['tax_report'][$missingField]);
        }
        if ($cancelled) {
            $metadata['tax_report']['tax_summary']['status'] = 'cancelled';
        }
        $attributes['metadata'] = json_encode($metadata);
        // Replace the template as well, so all events start with the same legacy metadata.
        $template->delete();
        foreach (array_chunk($invoiceIds, 100) as $chunk) {
            TransactionEvent::insert(array_map(fn ($id) => array_replace($attributes, ['invoice_id' => $id]), $chunk));
        }
        return TransactionEvent::whereIn('invoice_id', $invoiceIds)->orderBy('id')->pluck('id')->all();
    }

    public function test_tax_period_report_build_data_preserves_balance_order_across_pages(): void
    {
        $this->makeTestData();
        [, $ids] = $this->seedPages('invoice', 'date');
        $this->seedTransactionEvents($ids);
        Invoice::whereIn('id', array_slice($ids, 2))->update(['balance' => 20]);
        $streams = [];
        $export = $this->observeStreams(TaxPeriodReport::class, [], $streams);
        $export->boot();
        $this->assertCount(1, $streams);
        $this->assertSame('streamQuery', $streams[0]->helper);
        $this->assertSame(array_merge(array_slice($ids, 2), array_slice($ids, 0, 2)), $streams[0]->ids);
        $this->assertCount(self::ROW_COUNT + 1, $export->getData()['invoices']);
    }

    public static function taxBackfills(): array
    {
        return [
            'classification' => ['backfillClassificationBreakdown', 'tax_details_by_classification'],
            'sales' => ['backfillSalesBreakdown', 'sales_breakdown'],
        ];
    }

    #[DataProvider('taxBackfills')]
    public function test_tax_backfills_process_every_eligible_event(string $method, string $field): void
    {
        $this->makeTestData();
        [, $ids] = $this->seedPages('invoice', 'date');
        $eventIds = $this->seedTransactionEvents($ids, $field);
        $streams = [];
        $export = $this->observeStreams(TaxPeriodReport::class, [], $streams);
        $export->start_date = '2020-01-01';
        $export->end_date = '2020-01-31';
        (new ReflectionMethod(TaxPeriodReport::class, $method))->invoke($export);
        $this->assertCount(1, $streams);
        $this->assertSame('streamQueryById', $streams[0]->helper);
        $this->assertSame($eventIds, $streams[0]->ids, 'Updating metadata must not skip the remaining eligible events');
        $this->assertSame(0, TransactionEvent::whereIn('id', $eventIds)->whereNull("metadata->tax_report->$field")->count());
    }

    public static function taxInitialization(): array
    {
        return ['active paid invoices' => [false], 'previously cancelled invoices' => [true]];
    }

    #[DataProvider('taxInitialization')]
    public function test_tax_initialization_streams_every_eligible_invoice(bool $cancelled): void
    {
        $this->makeTestData();
        [, $ids] = $this->seedPages('invoice', 'date');
        Invoice::whereIn('id', $ids)->update(['status_id' => $cancelled ? Invoice::STATUS_CANCELLED : Invoice::STATUS_PAID]);
        if ($cancelled) {
            $this->seedTransactionEvents($ids, null, true);
        }
        $streams = [];
        $export = $this->observeStreams(TaxPeriodReport::class, [], $streams);
        $export->start_date = '2020-01-01';
        $export->end_date = '2020-01-31';
        (new ReflectionMethod(TaxPeriodReport::class, 'initializeData'))->invoke($export);
        $this->assertCount(5, $streams, 'Three invoice sweeps and two event backfills');
        $this->assertSame(['streamQueryById', 'streamQuery', 'streamQueryById', 'streamQueryById', 'streamQueryById'], array_column($streams, 'helper'));
        // Check the non-mutating cash sweep even if the first sweep has a regression.
        $this->assertSame($cancelled ? [] : $ids, $streams[1]->ids);
        $this->assertSame($cancelled ? $ids : [], $streams[2]->ids);
        $this->assertSame($cancelled ? [] : $ids, $streams[0]->ids, 'Creating transaction events must not skip remaining invoices');
    }

    public function test_cancellation_sweep_processes_all_invoices_as_events_remove_them_from_the_query(): void
    {
        $this->makeTestData();
        [, $ids] = $this->seedPages('invoice', 'date');
        $eventIds = $this->seedTransactionEvents($ids);
        // An existing non-terminal event at end_date excludes these from the first
        // sweep, but the cancellation sweep must still record the new terminal state.
        TransactionEvent::whereIn('id', $eventIds)->update(['period' => '2020-01-31']);
        Invoice::whereIn('id', $ids)->update(['status_id' => Invoice::STATUS_CANCELLED, 'balance' => 0]);

        $streams = [];
        $export = $this->observeStreams(TaxPeriodReport::class, [], $streams);
        $export->start_date = '2020-01-01';
        $export->end_date = '2020-01-31';
        $initialize = new ReflectionMethod(TaxPeriodReport::class, 'initializeData');
        $initialize->invoke($export);

        $this->assertCount(5, $streams);
        $this->assertSame([], $streams[0]->ids);
        $this->assertSame([], $streams[1]->ids);
        $this->assertSame($ids, $streams[2]->ids);
        $this->assertSame('streamQueryById', $streams[2]->helper);
        $terminalEvents = TransactionEvent::whereIn('invoice_id', $ids)
            ->where('period', '2020-01-31')
            ->where('metadata->tax_report->tax_summary->status', 'cancelled');
        $this->assertSame(self::ROW_COUNT, $terminalEvents->count());

        $streams = [];
        $initialize->invoke($export);
        $this->assertSame([], $streams[2]->ids, 'Completed cancellations must not be processed again');
        $this->assertSame(self::ROW_COUNT, $terminalEvents->count());
    }

    public function test_tax_period_id_stream_clones_the_query_and_preserves_filters_and_eager_loading(): void
    {
        $this->makeTestData();
        [, $ids] = $this->seedPages('invoice', 'date');
        $export = new TaxPeriodReport($this->company, $this->payload([]), true);
        $query = Invoice::whereIn('invoices.id', $ids)->with('client')->orderByDesc('date');
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        $method = new ReflectionMethod(TaxPeriodReport::class, 'streamQueryById');
        $connection = $query->getConnection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $rows = $method->invoke($export, $query);
            $this->assertSame([], $connection->getQueryLog(), 'Creating an ID stream must stay lazy');
            $actual = $rows->map(function (Invoice $invoice) {
                $this->assertTrue($invoice->relationLoaded('client'));
                return $invoice->id;
            })->all();
            $pages = array_values(array_filter($connection->getQueryLog(), fn ($q) =>
                str_contains($q['query'], 'from `invoices`') && str_contains($q['query'], 'limit 500')
            ));
        } finally {
            $connection->disableQueryLog();
        }
        $this->assertSame($ids, $actual);
        $this->assertCount(2, $pages);
        $this->assertStringContainsString('`invoices`.`id` > ?', $pages[1]['query']);
        foreach ($pages as $page) {
            $this->assertStringNotContainsString('offset', $page['query']);
            $this->assertStringContainsString('order by `invoices`.`id` asc', $page['query']);
        }
        $this->assertSame($sql, $query->toSql());
        $this->assertSame($bindings, $query->getBindings());
        $this->assertSame($ids, $rows->pluck('id')->all(), 'The stream must be reusable');
        $this->assertSame([], $method->invoke($export, (clone $query)->whereRaw('1 = 0'))->all());
    }

    public function test_caller_inventory_requires_coverage_for_new_stream_query_calls(): void
    {
        $expected = [
            'BaseExport::queueDocuments' => 1,
            'EInvoiceReport::run' => 2,
            'TaxPeriodReport::initializeData' => 3,
            'TaxPeriodReport::backfillClassificationBreakdown' => 1,
            'TaxPeriodReport::backfillSalesBreakdown' => 1,
            'TaxPeriodReport::buildData' => 1,
        ];
        foreach (self::exports() as [$class]) {
            $expected["$class::run"] = $class === 'InvoiceExport' ? 2 : 1;
            if ($class !== 'ProductSalesExport') {
                $expected["$class::returnJson"] = 1;
            }
        }
        $actual = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || !preg_match('/->streamQuery(?:ById)?\s*\(/', file_get_contents($file->getPathname()))) {
                continue;
            }
            $className = 'App\\' . str_replace('/', '\\', substr($file->getPathname(), strlen(app_path()) + 1, -4));
            $class = new ReflectionClass($className);
            if ($className !== BaseExport::class && !$class->isSubclassOf(BaseExport::class)) {
                continue; // CSVIngest owns a separate helper and is unaffected by this change.
            }
            $lines = file($file->getPathname());
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class->getName() || $method->getFileName() !== $file->getPathname()) {
                    continue;
                }
                $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
                $count = preg_match_all('/->streamQuery(?:ById)?\s*\(/', $source);
                if ($count) {
                    $actual[$class->getShortName() . '::' . $method->getName()] = $count;
                }
            }
        }
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual, 'Add a pagination scenario whenever a new caller is introduced.');
    }
}
