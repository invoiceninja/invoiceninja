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

namespace Tests\Feature\Import\CSV;

use App\Import\Definitions\ProductMap;
use App\Import\Definitions\PurchaseOrderMap;
use App\Import\Providers\Csv;
use App\Import\Transformer\Csv\ExpenseTransformer;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Size;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class CsvImportMappingRegressionTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testExpenseCurrencyMappingIsPersisted(): void
    {
        $currency = app('currencies')->first(
            fn ($currency) => (string) $currency->id !== (string) $this->company->settings->currency_id
        );

        $this->assertNotNull($currency);

        $publicNotes = 'Expense currency mapping '.Str::random(12);

        $importer = $this->runImport(
            'expense',
            ['Amount', 'Currency', 'Public Notes'],
            ['12.34', $currency->code, $publicNotes],
            [
                0 => 'expense.amount',
                1 => 'expense.currency',
                2 => 'expense.public_notes',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $expense = Expense::query()
            ->where('company_id', $this->company->id)
            ->where('public_notes', $publicNotes)
            ->firstOrFail();

        $this->assertSame((string) $currency->id, (string) $expense->currency_id);
    }

    public function testExpenseFalseInclusiveTaxesMappingIsPersisted(): void
    {
        $publicNotes = 'Expense inclusive taxes mapping '.Str::random(12);

        $importer = $this->runImport(
            'expense',
            ['Amount', 'Uses Inclusive Taxes', 'Public Notes'],
            ['12.34', 'False', $publicNotes],
            [
                0 => 'expense.amount',
                1 => 'expense.uses_inclusive_taxes',
                2 => 'expense.public_notes',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $expense = Expense::query()
            ->where('company_id', $this->company->id)
            ->where('public_notes', $publicNotes)
            ->firstOrFail();

        $this->assertFalse((bool) $expense->uses_inclusive_taxes);
    }

    #[DataProvider('falseBooleanProvider')]
    public function testExpenseInclusiveTaxesDifferentiatesFalseValues(mixed $value): void
    {
        $transformed = (new ExpenseTransformer($this->company))->transform([
            'expense.amount' => '12.34',
            'expense.uses_inclusive_taxes' => $value,
        ]);

        $this->assertFalse($transformed['uses_inclusive_taxes']);
    }

    /** @return array<string, array{false|string}> */
    public static function falseBooleanProvider(): array
    {
        return [
            'boolean false' => [false],
            'lowercase false string' => ['false'],
            'title case false string' => ['False'],
        ];
    }

    public function testProductMaxQuantityMappingIsPersisted(): void
    {
        $this->assertContains('product.max_quantity', ProductMap::importable());

        $productKey = 'max-quantity-'.Str::random(12);

        $importer = $this->runImport(
            'product',
            ['Product Key', 'Max Quantity'],
            [$productKey, '25'],
            [
                0 => 'product.product_key',
                1 => 'product.max_quantity',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $product = Product::query()
            ->where('company_id', $this->company->id)
            ->where('product_key', $productKey)
            ->firstOrFail();

        $this->assertSame(25, (int) $product->max_quantity);
    }

    public function testProductTaxCategoryMappingIsPersisted(): void
    {
        $productKey = 'tax-category-'.Str::random(12);

        $importer = $this->runImport(
            'product',
            ['Product Key', 'Tax Category'],
            [$productKey, (string) Product::PRODUCT_TYPE_ZERO_RATED],
            [
                0 => 'product.product_key',
                1 => 'product.tax_category',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $product = Product::query()
            ->where('company_id', $this->company->id)
            ->where('product_key', $productKey)
            ->firstOrFail();

        $this->assertSame(Product::PRODUCT_TYPE_ZERO_RATED, (int) $product->tax_id);
    }

    public function testUnmappedInvoiceNumbersCreateSeparateAutoNumberedInvoices(): void
    {
        $marker = 'blank-number-' . Str::random(12);
        $beforeId = (int) Invoice::query()->max('id');

        $importer = $this->runRowsImport(
            'invoice',
            ['Number', 'Client', 'Public Notes', 'Product', 'Cost', 'Quantity'],
            [
                ['', $this->client->name, $marker . '-one', 'ITEM-ONE', '10', '1'],
                ['', $this->client->name, $marker . '-two', 'ITEM-TWO', '20', '1'],
            ],
            [
                1 => 'client.name',
                2 => 'invoice.public_notes',
                3 => 'item.product_key',
                4 => 'item.cost',
                5 => 'item.quantity',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $invoices = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('id', '>', $beforeId)
            ->whereIn('public_notes', [$marker . '-one', $marker . '-two'])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $invoices);
        $this->assertNotSame($invoices[0]->number, $invoices[1]->number);
        $this->assertSame([1, 1], $invoices->map(fn (Invoice $invoice): int => count($invoice->line_items))->all());
    }

    public function testClientIndustrySizeAndPaymentTermsMappingsArePersisted(): void
    {
        $industry = Industry::query()->firstOrFail();
        $size = Size::query()->firstOrFail();
        $clientName = 'Client mappings '.Str::random(12);

        $importer = $this->runImport(
            'client',
            ['Name', 'Industry', 'Size', 'Payment Terms'],
            [$clientName, (string) $industry->id, (string) $size->id, '45'],
            [
                0 => 'client.name',
                1 => 'client.industry_id',
                2 => 'client.size_id',
                3 => 'client.payment_terms',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $client = Client::query()
            ->where('company_id', $this->company->id)
            ->where('name', $clientName)
            ->firstOrFail();

        $this->assertSame(
            [
                'industry_id' => (string) $industry->id,
                'size_id' => (string) $size->id,
                'payment_terms' => '45',
            ],
            [
                'industry_id' => (string) $client->industry_id,
                'size_id' => (string) $client->size_id,
                'payment_terms' => (string) ($client->settings->payment_terms ?? ''),
            ]
        );
    }

    public function testPurchaseOrderCurrencyMappingIsPersisted(): void
    {
        $this->assertContains('purchase_order.currency_id', PurchaseOrderMap::importable());

        $currency = app('currencies')->first(
            fn ($currency) => (string) $currency->id !== (string) $this->company->settings->currency_id
        );

        $this->assertNotNull($currency);

        $number = 'PO-currency-'.Str::random(12);

        $importer = $this->runImport(
            'purchase_order',
            ['Number', 'Vendor', 'Currency', 'Item Cost', 'Item Quantity'],
            [$number, $this->vendor->name, $currency->code, '10', '1'],
            [
                0 => 'purchase_order.number',
                1 => 'vendor.name',
                2 => 'purchase_order.currency_id',
                3 => 'item.cost',
                4 => 'item.quantity',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $purchaseOrder = PurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->where('number', $number)
            ->firstOrFail();

        $this->assertSame((string) $currency->id, (string) $purchaseOrder->currency_id);
    }

    #[DataProvider('documentMappingProvider')]
    public function testDocumentMappingIsPersisted(
        string $entity,
        string $model,
        string $mapPrefix,
        string $mapping,
        mixed $expected
    ): void {
        $number = strtoupper($entity).'-mapping-'.Str::random(12);
        $partyKey = $entity === 'purchase_order' ? 'vendor.name' : 'client.name';
        $partyName = $entity === 'purchase_order' ? $this->vendor->name : $this->client->name;
        $numberKey = $mapPrefix.'.number';
        $statusValue = $entity === 'recurring_invoice' ? 'true' : 'false';

        $headers = ['Number', 'Party', 'Uses Inclusive Taxes', 'Is Sent', 'Exchange Rate', 'Item Cost', 'Item Quantity'];
        $row = [$number, $partyName, 'true', $statusValue, '2.75', '10', '1'];
        $columnMap = [
            0 => $numberKey,
            1 => $partyKey,
            2 => $mapPrefix.'.uses_inclusive_taxes',
            3 => $mapPrefix.'.is_sent',
            4 => $mapPrefix.'.exchange_rate',
            5 => 'item.cost',
            6 => 'item.quantity',
        ];

        if ($entity === 'recurring_invoice') {
            $headers[] = 'Frequency';
            $row[] = 'monthly';
            $columnMap[] = 'invoice.frequency_id';
        }

        $importer = $this->runImport($entity, $headers, $row, $columnMap);

        $this->assertEmpty($importer->getErrors());

        $document = $model::query()
            ->where('company_id', $this->company->id)
            ->where('number', $number)
            ->firstOrFail();

        $actual = match ($mapping) {
            'uses_inclusive_taxes' => (bool) $document->uses_inclusive_taxes,
            'is_sent' => (int) $document->status_id,
            'exchange_rate' => (float) $document->exchange_rate,
        };

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, class-string, string, string, bool|float|int}>
     */
    public static function documentMappingProvider(): array
    {
        return [
            'invoice uses inclusive taxes' => [
                'invoice', Invoice::class, 'invoice', 'uses_inclusive_taxes', true,
            ],
            'invoice is sent false' => [
                'invoice', Invoice::class, 'invoice', 'is_sent', Invoice::STATUS_DRAFT,
            ],
            'invoice exchange rate' => [
                'invoice', Invoice::class, 'invoice', 'exchange_rate', 2.75,
            ],
            'quote uses inclusive taxes' => [
                'quote', Quote::class, 'quote', 'uses_inclusive_taxes', true,
            ],
            'quote is sent false' => [
                'quote', Quote::class, 'quote', 'is_sent', Quote::STATUS_DRAFT,
            ],
            'quote exchange rate' => [
                'quote', Quote::class, 'quote', 'exchange_rate', 2.75,
            ],
            'recurring invoice uses inclusive taxes' => [
                'recurring_invoice', RecurringInvoice::class, 'invoice', 'uses_inclusive_taxes', true,
            ],
            'recurring invoice is sent true' => [
                'recurring_invoice', RecurringInvoice::class, 'invoice', 'is_sent', RecurringInvoice::STATUS_ACTIVE,
            ],
            'recurring invoice exchange rate' => [
                'recurring_invoice', RecurringInvoice::class, 'invoice', 'exchange_rate', 2.75,
            ],
            'purchase order uses inclusive taxes' => [
                'purchase_order', PurchaseOrder::class, 'purchase_order', 'uses_inclusive_taxes', true,
            ],
            'purchase order is sent false' => [
                'purchase_order', PurchaseOrder::class, 'purchase_order', 'is_sent', PurchaseOrder::STATUS_DRAFT,
            ],
            'purchase order exchange rate' => [
                'purchase_order', PurchaseOrder::class, 'purchase_order', 'exchange_rate', 2.75,
            ],
        ];
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $row
     * @param array<int, string> $columnMap
     */
    private function runImport(string $entity, array $headers, array $row, array $columnMap): Csv
    {
        return $this->runRowsImport($entity, $headers, [$row], $columnMap);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @param array<int, string> $columnMap
     */
    private function runRowsImport(string $entity, array $headers, array $rows, array $columnMap): Csv
    {
        $writer = Writer::createFromString();
        $writer->insertOne($headers);
        $writer->insertAll($rows);

        $hash = Str::random(32);
        $data = [
            'hash' => $hash,
            'column_map' => [$entity => ['mapping' => $columnMap]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash.'-'.$entity, base64_encode($writer->toString()), 360);

        $importer = new Csv($data, $this->company);
        $importer->import($entity);

        return $importer;
    }
}
