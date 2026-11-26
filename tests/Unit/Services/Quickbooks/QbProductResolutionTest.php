<?php

namespace Tests\Unit\Services\Quickbooks;

use App\DataMapper\ProductSync;
use App\Factory\InvoiceItemFactory;
use App\Models\Product;
use App\Services\Quickbooks\Models\QbProduct;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\SdkWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class QbProductResolutionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testEmptyMappingAfterDuplicateNameFailureCanResolveExistingQuickbooksProduct(): void
    {
        $product = $this->makeHardwareProduct(new ProductSync([
            'qb_id' => '',
            'qb_status_message' => 'This name already exists in QuickBooks (QB 6240). Use a unique name or link the existing record.',
        ]));
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->andReturn([(object) [
            'Id' => '30',
            'Name' => 'Hardware',
            'Active' => true,
            'Type' => 'NonInventory',
        ]]);

        $qb_id = $this->resolver($sdk)->findOrCreateProduct($this->hardwareLine());

        $this->assertSame('30', $qb_id, 'An empty ProductSync qb_id must not be treated as a resolved product.');
        $this->assertSame('Hardware', $product->fresh()->product_key);
    }

    public function testExistingNonemptyMappingIsRetained(): void
    {
        $this->makeHardwareProduct(new ProductSync(['qb_id' => '30']));
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldNotReceive('query');
        $sdk->shouldNotReceive('add');

        $this->assertSame('30', $this->resolver($sdk)->findOrCreateProduct($this->hardwareLine()));
    }

    public function testFailedProductResolutionPreservesNinjaProduct(): void
    {
        $product = $this->makeHardwareProduct(null);
        $original_attributes = $product->getRawOriginal();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->andThrow(new RuntimeException('QuickBooks query timed out'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('QuickBooks query timed out');

        try {
            $this->resolver($sdk)->findOrCreateProduct($this->hardwareLine());
        } finally {
            $this->assertSame($original_attributes, $product->fresh()->getRawOriginal());
        }
    }

    private function makeHardwareProduct(?ProductSync $sync): Product
    {
        $product = Product::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'product_key' => 'Hardware',
            'price' => 1659,
        ]);
        $product->sync = $sync;
        $product->saveQuietly();

        return $product->fresh();
    }

    private function hardwareLine(): object
    {
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = 'Hardware';
        $line_item->quantity = 1;
        $line_item->cost = 1659;

        return $line_item;
    }

    private function resolver(SdkWrapper $sdk): QbProduct
    {
        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $this->company;
        $service->shouldReceive('sdk')->andReturn($sdk);

        return new QbProduct($service);
    }
}
