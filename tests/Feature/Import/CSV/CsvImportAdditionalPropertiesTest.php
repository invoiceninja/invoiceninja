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

use App\Import\Definitions\ExpenseMap;
use App\Import\Definitions\InvoiceMap;
use App\Import\Definitions\ProductMap;
use App\Import\Definitions\PurchaseOrderMap;
use App\Import\Definitions\QuoteMap;
use App\Import\Definitions\RecurringInvoiceMap;
use App\Import\Definitions\VendorMap;
use App\Import\Providers\Csv;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Vendor;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class CsvImportAdditionalPropertiesTest extends TestCase
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

    public function testVendorAdditionalPropertiesAreMappedAndPersisted(): void
    {
        $this->assertMappingsAreImportable(VendorMap::importable(), [
            'vendor.language_id',
            'vendor.classification',
            'vendor.is_tax_exempt',
        ]);

        $vendorName = 'Vendor properties '.Str::random(12);
        $languageId = (string) $this->company->settings->language_id;

        $importer = $this->runImport(
            'vendor',
            ['Name', 'Language', 'Classification', 'Tax Exempt'],
            [$vendorName, $languageId, 'business', 'true'],
            [
                0 => 'vendor.name',
                1 => 'vendor.language_id',
                2 => 'vendor.classification',
                3 => 'vendor.is_tax_exempt',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $vendor = Vendor::query()
            ->where('company_id', $this->company->id)
            ->where('name', $vendorName)
            ->firstOrFail();

        $this->assertSame($languageId, (string) $vendor->language_id);
        $this->assertSame('business', $vendor->classification);
        $this->assertTrue((bool) $vendor->is_tax_exempt);
    }

    public function testProductStockNotificationPropertiesAreMappedAndPersisted(): void
    {
        $this->assertMappingsAreImportable(ProductMap::importable(), [
            'product.stock_notification',
            'product.stock_notification_threshold',
        ]);

        $productKey = 'stock-notification-'.Str::random(12);

        $importer = $this->runImport(
            'product',
            ['Product Key', 'Stock Notification', 'Notification Threshold'],
            [$productKey, 'true', '7'],
            [
                0 => 'product.product_key',
                1 => 'product.stock_notification',
                2 => 'product.stock_notification_threshold',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $product = Product::query()
            ->where('company_id', $this->company->id)
            ->where('product_key', $productKey)
            ->firstOrFail();

        $this->assertTrue((bool) $product->stock_notification);
        $this->assertSame(7, (int) $product->stock_notification_threshold);
    }

    public function testExpenseAdditionalPropertiesAreMappedAndPersisted(): void
    {
        $this->assertMappingsAreImportable(ExpenseMap::importable(), [
            'expense.exchange_rate',
            'expense.foreign_amount',
            'expense.should_be_invoiced',
        ]);

        $publicNotes = 'Expense properties '.Str::random(12);

        $importer = $this->runImport(
            'expense',
            ['Client', 'Amount', 'Foreign Amount', 'Exchange Rate', 'Should Be Invoiced', 'Public Notes'],
            [$this->client->name, '20', '25', '1.25', 'false', $publicNotes],
            [
                0 => 'expense.client',
                1 => 'expense.amount',
                2 => 'expense.foreign_amount',
                3 => 'expense.exchange_rate',
                4 => 'expense.should_be_invoiced',
                5 => 'expense.public_notes',
            ]
        );

        $this->assertEmpty($importer->getErrors());

        $expense = Expense::query()
            ->where('company_id', $this->company->id)
            ->where('public_notes', $publicNotes)
            ->firstOrFail();

        $this->assertSame(25.0, (float) $expense->foreign_amount);
        $this->assertSame(1.25, (float) $expense->exchange_rate);
        $this->assertFalse((bool) $expense->should_be_invoiced);
    }

    /**
     * @param class-string $model
     * @param class-string $map
     */
    #[DataProvider('invoiceLikeEntityProvider')]
    public function testInvoiceLikeLineItemPropertiesAreMappedAndPersisted(
        string $entity,
        string $model,
        string $map,
        string $mapPrefix
    ): void {
        $this->assertMappingsAreImportable($map::importable(), [
            'item.tax_id',
            'item.product_cost',
        ]);

        $number = strtoupper($entity).'-line-item-'.Str::random(12);
        $partyKey = $entity === 'purchase_order' ? 'vendor.name' : 'client.name';
        $partyName = $entity === 'purchase_order' ? $this->vendor->name : $this->client->name;

        $headers = ['Number', 'Party', 'Item Cost', 'Item Quantity', 'Tax Category', 'Product Cost'];
        $row = [$number, $partyName, '10', '2', (string) Product::PRODUCT_TYPE_ZERO_RATED, '4.25'];
        $columnMap = [
            0 => $mapPrefix.'.number',
            1 => $partyKey,
            2 => 'item.cost',
            3 => 'item.quantity',
            4 => 'item.tax_id',
            5 => 'item.product_cost',
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
        $lineItem = $document->line_items[0];

        $this->assertSame((string) Product::PRODUCT_TYPE_ZERO_RATED, (string) $lineItem->tax_id);
        $this->assertSame(4.25, (float) $lineItem->product_cost);
    }

    /**
     * @return array<string, array{string, class-string, class-string, string}>
     */
    public static function invoiceLikeEntityProvider(): array
    {
        return [
            'invoice' => ['invoice', Invoice::class, InvoiceMap::class, 'invoice'],
            'quote' => ['quote', Quote::class, QuoteMap::class, 'quote'],
            'recurring invoice' => [
                'recurring_invoice', RecurringInvoice::class, RecurringInvoiceMap::class, 'invoice',
            ],
            'purchase order' => [
                'purchase_order', PurchaseOrder::class, PurchaseOrderMap::class, 'purchase_order',
            ],
        ];
    }

    /**
     * @param array<int, string> $importable
     * @param array<int, string> $expected
     */
    private function assertMappingsAreImportable(array $importable, array $expected): void
    {
        $this->assertSame([], array_values(array_diff($expected, $importable)));
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $row
     * @param array<int, string> $columnMap
     */
    private function runImport(string $entity, array $headers, array $row, array $columnMap): Csv
    {
        $writer = Writer::createFromString();
        $writer->insertOne($headers);
        $writer->insertOne($row);

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
