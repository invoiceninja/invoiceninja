<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Import\CSV;

use App\Import\Providers\Csv;
use App\Import\Transformer\BaseTransformer;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *  App\Import\Providers\Csv - PurchaseOrder Import
 */
class PurchaseOrderImportTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testPurchaseOrderImport()
    {
        /* Need to import vendors first */
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/vendors.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            1 => 'vendor.balance',
            2 => 'vendor.paid_to_date',
            0 => 'vendor.name',
            19 => 'vendor.currency_id',
            20 => 'vendor.public_notes',
            21 => 'vendor.private_notes',
            22 => 'contact.first_name',
            23 => 'contact.last_name',
            24 => 'contact.email',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['vendor' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-vendor', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);
        $this->assertInstanceOf(Csv::class, $csv_importer);

        $csv_importer->import('vendor');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertNotNull($base_transformer->getVendorId('Ludwig Krajcik DVM'));

        /* Vendor import verified, now import purchase orders */
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/purchase_orders.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'purchase_order.number',
            1 => 'vendor.name',
            2 => 'purchase_order.date',
            3 => 'purchase_order.due_date',
            4 => 'purchase_order.amount',
            5 => 'purchase_order.discount',
            6 => 'purchase_order.po_number',
            7 => 'purchase_order.public_notes',
            8 => 'purchase_order.private_notes',
            9 => 'purchase_order.status',
            10 => 'item.product_key',
            11 => 'item.notes',
            12 => 'item.cost',
            13 => 'item.quantity',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['purchase_order' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-purchase_order', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $csv_importer->import('purchase_order');

        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-001'));
        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-002'));
        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-003'));
        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-004'));

        /* Verify correct number of purchase orders created (PO-004 has 2 line items but should be 1 PO) */
        $purchase_order_count = PurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->whereIn('number', ['PO-001', 'PO-002', 'PO-003', 'PO-004'])
            ->count();
        $this->assertEquals(4, $purchase_order_count);

        /* Verify PO-004 has 2 line items (grouped) */
        $po_004 = PurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->where('number', 'PO-004')
            ->first();
        $this->assertNotNull($po_004);
        $this->assertCount(2, $po_004->line_items);

        /* Verify vendor assignment */
        $vendor = Vendor::query()
            ->where('company_id', $this->company->id)
            ->where('name', 'Ludwig Krajcik DVM')
            ->first();
        $this->assertNotNull($vendor);

        $po_001 = PurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->where('number', 'PO-001')
            ->first();
        $this->assertEquals($vendor->id, $po_001->vendor_id);

        /* Verify status mapping */
        $po_002 = PurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->where('number', 'PO-002')
            ->first();
        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $po_002->status_id);

        /* Verify PO fields */
        $this->assertEquals('EXT-PO-001', $po_001->po_number);
        $this->assertEquals('Public note for PO1', $po_001->public_notes);
        $this->assertEquals('Private note for PO1', $po_001->private_notes);
    }

    public function testPurchaseOrderDuplicateImport()
    {
        /* Import vendors first */
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/vendors.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            0 => 'vendor.name',
            22 => 'contact.first_name',
            23 => 'contact.last_name',
            24 => 'contact.email',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['vendor' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-vendor', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);
        $csv_importer->import('vendor');

        /* Import purchase orders */
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/purchase_orders.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'purchase_order.number',
            1 => 'vendor.name',
            2 => 'purchase_order.date',
            3 => 'purchase_order.due_date',
            4 => 'purchase_order.amount',
            5 => 'purchase_order.discount',
            6 => 'purchase_order.po_number',
            7 => 'purchase_order.public_notes',
            8 => 'purchase_order.private_notes',
            9 => 'purchase_order.status',
            10 => 'item.product_key',
            11 => 'item.notes',
            12 => 'item.cost',
            13 => 'item.quantity',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['purchase_order' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-purchase_order', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);
        $csv_importer->import('purchase_order');

        $count_before = PurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->count();

        /* Import the same purchase orders again — duplicates should be skipped */
        $hash2 = Str::random(32);
        $data['hash'] = $hash2;
        $data['column_map'] = ['purchase_order' => ['mapping' => $column_map]];

        Cache::put($hash2 . '-purchase_order', base64_encode($csv), 360);

        $csv_importer2 = new Csv($data, $this->company);
        $csv_importer2->import('purchase_order');

        $count_after = PurchaseOrder::query()
            ->where('company_id', $this->company->id)
            ->count();

        $this->assertEquals($count_before, $count_after);
    }

    public function testPurchaseOrderVendorAutoCreate()
    {
        /* Import purchase orders without pre-importing vendors — vendors should be auto-created */
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/purchase_orders.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'purchase_order.number',
            1 => 'vendor.name',
            2 => 'purchase_order.date',
            3 => 'purchase_order.due_date',
            4 => 'purchase_order.amount',
            5 => 'purchase_order.discount',
            6 => 'purchase_order.po_number',
            7 => 'purchase_order.public_notes',
            8 => 'purchase_order.private_notes',
            9 => 'purchase_order.status',
            10 => 'item.product_key',
            11 => 'item.notes',
            12 => 'item.cost',
            13 => 'item.quantity',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['purchase_order' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-purchase_order', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);
        $csv_importer->import('purchase_order');

        $base_transformer = new BaseTransformer($this->company);

        /* Vendors should have been auto-created */
        $this->assertNotNull($base_transformer->getVendorId('Ludwig Krajcik DVM'));
        $this->assertNotNull($base_transformer->getVendorId('Bradly Jaskolski Sr.'));
        $this->assertNotNull($base_transformer->getVendorId('Mr. Dustin Stehr I'));

        /* Purchase orders should have been created successfully */
        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-001'));
        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-002'));
        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-003'));
        $this->assertTrue($base_transformer->hasPurchaseOrder('PO-004'));
    }
}
