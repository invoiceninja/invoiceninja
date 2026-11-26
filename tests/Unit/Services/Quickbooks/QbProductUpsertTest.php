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
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class QbProductUpsertTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    #[DataProvider('mappedProducts')]
    public function testProductPushUpdatesExistingItemWithoutInsert(?string $qb_id, int $tax_id): void
    {
        $product = $this->hardwareProduct($qb_id);
        $product->tax_id = $tax_id;
        $product->saveQuietly();
        $sdk = Mockery::mock(SdkWrapper::class);
        if (empty($qb_id)) {
            $sdk->shouldReceive('query')->once()->andReturn([$this->remoteItem()]);
        } else {
            $sdk->shouldNotReceive('query');
        }
        $sdk->shouldReceive('findById')->once()->with('Item', '30')->andReturn(\QuickBooksOnline\API\Facades\Item::create(['Id' => '30', 'Name' => 'Hardware', 'Type' => 'NonInventory', 'Active' => true, 'SyncToken' => '2']));
        $sdk->shouldReceive('update')->once()->with(Mockery::on(function ($item) use ($tax_id): bool {
            return (data_get($item, 'Id.value') ?? data_get($item, 'Id')) === '30'
                && $item->SyncToken === '2'
                && (float) $item->UnitPrice === 1659.0
                && filter_var($item->Taxable, FILTER_VALIDATE_BOOLEAN) === ($tax_id !== 5);
        }))->andReturn($this->remoteItem());
        $sdk->shouldNotReceive('add');

        $this->resolver($sdk)->syncToForeign([$product]);

        $product->refresh();
        $this->assertSame('30', $product->sync->qb_id);
        $this->assertSame('', $product->sync->qb_status_message);
        $this->assertEquals(1659, $product->price);
        $this->assertSame('Original Ninja description', $product->notes);
    }

    public static function mappedProducts(): array
    {
        return [
            'no mapping' => [null, 1],
            'empty mapping' => ['', 1],
            'existing mapping' => ['30', 1],
            'tax exemption retained' => ['30', 5],
        ];
    }

    public function testProductPushInsertsOnceWhenNoExistingItemMatches(): void
    {
        $product = $this->hardwareProduct(null);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->once()->andReturn([]);
        $sdk->shouldReceive('add')->once()->andReturn(\QuickBooksOnline\API\Facades\Item::create(['Id' => '30', 'Name' => 'Hardware', 'Type' => 'NonInventory']));
        $sdk->shouldNotReceive('update');
        $sdk->shouldNotReceive('findById');

        $this->resolver($sdk)->syncToForeign([$product]);

        $product->refresh();
        $this->assertSame('30', $product->sync->qb_id);
        $this->assertEquals(1659, $product->price);
    }

    public function testInvoiceLookupPersistsMappingWithoutUpdatingCatalogue(): void
    {
        $product = $this->hardwareProduct('');
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->once()->andReturn($this->remoteItem());
        $sdk->shouldNotReceive('add');
        $sdk->shouldNotReceive('update');
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = 'Hardware';
        $line_item->cost = 999;

        $this->assertSame('30', $this->resolver($sdk)->findOrCreateProduct($line_item));
        $this->assertSame('30', $product->fresh()->sync->qb_id);
        $this->assertEquals(1659, $product->fresh()->price);
    }

    #[DataProvider('invalidRemoteItems')]
    public function testInvalidMappedItemPreservesMappingAndNinjaFields(string $type, mixed $active): void
    {
        $product = $this->hardwareProduct('30');
        $sdk = Mockery::mock(SdkWrapper::class);
        $remote_item = $this->remoteItem();
        $remote_item->Type = $type;
        $remote_item->Active = $active;
        $sdk->shouldReceive('findById')->once()->andReturn($remote_item);
        $sdk->shouldNotReceive('query');
        $sdk->shouldNotReceive('add');
        $sdk->shouldNotReceive('update');
        $this->expectException(RuntimeException::class);

        try {
            $this->resolver($sdk)->syncToForeign([$product]);
        } finally {
            $product->refresh();
            $this->assertSame('30', $product->sync->qb_id);
            $this->assertNotEmpty($product->sync->qb_status_message);
            $this->assertEquals(1659, $product->price);
            $this->assertSame('Original Ninja description', $product->notes);
        }
    }

    public static function invalidRemoteItems(): array
    {
        return [
            'category' => ['Category', true],
            'group' => ['Group', true],
            'inactive' => ['NonInventory', 'false'],
        ];
    }

    public function testMissingMappedItemIsNotRecreated(): void
    {
        $product = $this->hardwareProduct('30');
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')->once()->andReturn(null);
        $sdk->shouldNotReceive('add');
        $sdk->shouldNotReceive('update');
        $this->expectException(RuntimeException::class);
        try {
            $this->resolver($sdk)->syncToForeign([$product]);
        } finally {
            $this->assertSame('30', $product->fresh()->sync->qb_id);
        }
    }

    public function testAmbiguousLookupDoesNotCreateOrLinkProduct(): void
    {
        $product = $this->hardwareProduct(null);
        $original_attributes = $product->getRawOriginal();
        $sdk = Mockery::mock(SdkWrapper::class);
        $second_item = $this->remoteItem();
        $second_item->Id = '31';
        $sdk->shouldReceive('query')->once()->andReturn([$this->remoteItem(), $second_item]);
        $sdk->shouldNotReceive('add');
        $sdk->shouldNotReceive('update');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Multiple QuickBooks items');
        try {
            $this->resolver($sdk)->syncToForeign([$product]);
        } finally {
            $this->assertSame($original_attributes, $product->fresh()->getRawOriginal());
        }
    }

    public function testFailedInsertPreservesNinjaFieldsAndRecordsFailure(): void
    {
        $product = $this->hardwareProduct(null);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->once()->andReturn([]);
        $sdk->shouldReceive('add')->once()->andThrow(new RuntimeException('QuickBooks duplicate name 6240'));
        $this->expectException(RuntimeException::class);
        try {
            $this->resolver($sdk)->syncToForeign([$product]);
        } finally {
            $product->refresh();
            $this->assertSame('', $product->sync->qb_id);
            $this->assertNotEmpty($product->sync->qb_status_message);
            $this->assertEquals(1659, $product->price);
            $this->assertSame('Original Ninja description', $product->notes);
        }
    }

    public function testFailedUpdateKeepsResolvedMappingAndNinjaFields(): void
    {
        $product = $this->hardwareProduct(null);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->once()->andReturn([$this->remoteItem()]);
        $sdk->shouldReceive('findById')->once()->andReturn($this->remoteItem());
        $sdk->shouldReceive('update')->once()->andThrow(new RuntimeException('QuickBooks unavailable'));
        $sdk->shouldNotReceive('add');
        $this->expectException(RuntimeException::class);
        try {
            $this->resolver($sdk)->syncToForeign([$product]);
        } finally {
            $product->refresh();
            $this->assertSame('30', $product->sync->qb_id);
            $this->assertNotEmpty($product->sync->qb_status_message);
            $this->assertEquals(1659, $product->price);
        }
    }

    #[DataProvider('collisionProductKeys')]
    public function testDuplicateNameCreatesOneFallbackAndPreservesMapping(string $product_key): void
    {
        $product = $this->hardwareProduct(null);
        $product->product_key = $product_key;
        $product->saveQuietly();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->with(Mockery::on(fn (string $query): bool => str_contains($query, "Name = '{$product_key}'")))
            ->times(mb_strlen($product_key) <= 100 ? 2 : 1)->andReturn([]);
        $sdk->shouldReceive('query')
            ->with(Mockery::on(fn (string $query): bool => str_contains($query, ' [IN-')))
            ->once()->andReturn([]);
        $names = [];
        $sdk->shouldReceive('add')->twice()->andReturnUsing(function ($item) use (&$names): object {
            $names[] = $item->Name;
            if (count($names) === 1) {
                throw $this->qbFailure('6240');
            }

            return (object) ['Id' => '30'];
        });
        $sdk->shouldNotReceive('update');

        $resolver = $this->resolver($sdk);
        $resolver->syncToForeign([$product]);

        $this->assertSame(mb_substr($product_key, 0, 100), $names[0]);
        $this->assertLessThanOrEqual(100, mb_strlen($names[1]));
        $this->assertMatchesRegularExpression('/ \[IN-[a-f0-9]{12}\]$/', $names[1]);
        $this->assertSame($product_key, $product->fresh()->product_key);
        $this->assertSame('30', $product->fresh()->sync->qb_id);
        $this->assertSame('', $product->fresh()->sync->qb_status_message);
        $this->assertSame('30', $resolver->findOrCreateProduct($product->fresh()));
    }

    public static function collisionProductKeys(): array
    {
        return [
            'short' => ['Hardware'],
            '100 characters' => [str_repeat('a', 100)],
            '101 characters' => [str_repeat('a', 100) . 'b'],
            'same prefix, different ending' => [str_repeat('a', 100) . 'c'],
            'multibyte boundary' => [str_repeat('é', 100)],
            'multibyte overflow' => [str_repeat('é', 101)],
        ];
    }

    public function testDuplicateNameReusesItemCreatedConcurrently(): void
    {
        $product = $this->hardwareProduct(null);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->twice()->andReturn([], [$this->remoteItem()]);
        $sdk->shouldReceive('add')->once()->andThrow($this->qbFailure('6240'));
        $sdk->shouldNotReceive('update');

        $this->resolver($sdk)->syncToForeign([$product]);

        $this->assertSame('30', $product->fresh()->sync->qb_id);
    }

    public function testDuplicateNameReusesExistingFallback(): void
    {
        $product = $this->hardwareProduct(null);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->with(Mockery::on(fn (string $query): bool => str_contains($query, "Name = 'Hardware'")))
            ->twice()->andReturn([]);
        $sdk->shouldReceive('query')
            ->with(Mockery::on(fn (string $query): bool => str_contains($query, ' [IN-')))
            ->once()->andReturn([$this->remoteItem()]);
        $sdk->shouldReceive('add')->once()->andThrow($this->qbFailure('6240'));

        $this->resolver($sdk)->syncToForeign([$product]);

        $this->assertSame('30', $product->fresh()->sync->qb_id);
        $this->assertSame('Hardware', $product->fresh()->product_key);
    }

    #[DataProvider('nonCollisionFailures')]
    public function testOtherCreationFailuresNeverRetry(string $code): void
    {
        $product = $this->hardwareProduct(null);
        $failure = $this->qbFailure($code);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->once()->andReturn([]);
        $sdk->shouldReceive('add')->once()->andThrow($failure);

        try {
            $this->resolver($sdk)->syncToForeign([$product]);
            $this->fail('The original creation error must be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
            $this->assertSame('', $product->fresh()->sync->qb_id);
            $this->assertStringContainsString($code, $product->fresh()->sync->qb_status_message);
        }
    }

    public static function nonCollisionFailures(): array
    {
        return [['2040'], ['2500'], ['6190']];
    }

    #[DataProvider('fallbackFailures')]
    public function testFallbackFailureIsReportedWithoutFurtherCreation(string $code): void
    {
        $product = $this->hardwareProduct(null);
        $failure = $this->qbFailure($code);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->times(3)->andReturn([]);
        $attempt = 0;
        $sdk->shouldReceive('add')->twice()->andReturnUsing(function () use (&$attempt, $failure): never {
            throw ++$attempt === 1 ? $this->qbFailure('6240') : $failure;
        });

        try {
            $this->resolver($sdk)->syncToForeign([$product]);
            $this->fail('Fallback failure must be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
            $this->assertSame('', $product->fresh()->sync->qb_id);
            $this->assertStringContainsString($code, $product->fresh()->sync->qb_status_message);
            $this->assertSame('Hardware', $product->fresh()->product_key);
        }
    }

    public static function fallbackFailures(): array
    {
        return [['2500'], ['6240']];
    }

    public function testDuplicateNameOnMappedUpdateNeverCreatesFallback(): void
    {
        $product = $this->hardwareProduct('30');
        $failure = $this->qbFailure('6240');
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')->once()->andReturn($this->remoteItem());
        $sdk->shouldReceive('update')->once()->andThrow($failure);
        $sdk->shouldNotReceive('query');
        $sdk->shouldNotReceive('add');
        $this->expectException(RuntimeException::class);

        $this->resolver($sdk)->syncToForeign([$product]);
    }

    private function qbFailure(string $code): RuntimeException
    {
        return new RuntimeException('Response Code:[400] with body: [<IntuitResponse><Fault type="ValidationFault"><Error code="'
            . $code . '"><Message>Product rejected</Message><Detail>Item failure detail</Detail></Error></Fault></IntuitResponse>]');
    }

    public function testLongKeysWithSamePrefixUseDistinctFallbacks(): void
    {
        $prefix = str_repeat('a', 100);
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->times(4)
            ->with(Mockery::on(fn (string $query): bool => !str_contains($query, "Name = '{$prefix}'")))
            ->andReturn([]);
        $names = [];
        $sdk->shouldReceive('add')->times(4)->andReturnUsing(function ($item) use (&$names): object {
            $names[] = $item->Name;
            if (count($names) % 2 === 1) {
                throw $this->qbFailure('6240');
            }

            return (object) ['Id' => (string) count($names)];
        });
        $resolver = $this->resolver($sdk);

        foreach (['b', 'c'] as $ending) {
            $product = $this->hardwareProduct(null);
            $product->product_key = $prefix . $ending;
            $product->saveQuietly();
            $resolver->syncToForeign([$product]);
        }

        $this->assertSame($names[0], $names[2]);
        $this->assertNotSame($names[1], $names[3]);
    }

    public function testMappedFallbackNameIsPreservedOnProductPush(): void
    {
        $product = $this->hardwareProduct('30');
        $remote_item = $this->remoteItem();
        $remote_item->Name = $this->hardwareFallbackName();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')->once()->andReturn($remote_item);
        $sdk->shouldReceive('update')->once()
            ->with(Mockery::on(fn ($item): bool => $item->Name === $remote_item->Name))
            ->andReturn($remote_item);
        $sdk->shouldNotReceive('query');
        $sdk->shouldNotReceive('add');

        $this->resolver($sdk)->syncToForeign([$product]);

        $this->assertSame('Hardware', $product->fresh()->product_key);
        $this->assertSame('30', $product->fresh()->sync->qb_id);
    }

    #[DataProvider('productPullMethods')]
    public function testProductPullPreservesOriginalKeyForFallbackName(string $method): void
    {
        $product = $this->hardwareProduct('30');
        $remote_item = $this->remoteItem();
        $remote_item->Name = $this->hardwareFallbackName();
        $sdk = Mockery::mock(SdkWrapper::class);
        $resolver = $this->resolver($sdk);

        if ($method === 'sync') {
            $sdk->shouldReceive('findById')->once()->with('Item', '30')->andReturn($remote_item);
            $resolver->service->shouldReceive('syncable')->once()->andReturn(true);
            $resolver->sync('30', now()->addDay()->toIso8601String());
        } else {
            $resolver->syncToNinja([$remote_item]);
        }

        $this->assertSame('Hardware', $product->fresh()->product_key);
        $this->assertSame('30', $product->fresh()->sync->qb_id);
    }

    public static function productPullMethods(): array
    {
        return [['sync'], ['syncToNinja']];
    }

    private function hardwareFallbackName(): string
    {
        return 'Hardware [IN-' . substr(hash('sha256', $this->company->id . ':Hardware'), 0, 12) . ']';
    }

    private function hardwareProduct(?string $qb_id): Product
    {
        $product = Product::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'product_key' => 'Hardware',
            'price' => 1659,
            'notes' => 'Original Ninja description',
            'tax_id' => 1,
        ]);
        $product->sync = $qb_id === null ? null : new ProductSync(['qb_id' => $qb_id]);
        $product->saveQuietly();
        return $product->fresh();
    }

    private function remoteItem(): object
    {
        return (object) ['Id' => '30', 'Name' => 'Hardware', 'Active' => true, 'Type' => 'NonInventory', 'SyncToken' => '2'];
    }

    private function resolver(SdkWrapper $sdk): QbProduct
    {
        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $this->company;
        $service->shouldReceive('sdk')->andReturn($sdk);
        $service->shouldReceive('getIncomeAccountId')->andReturn('6');
        return new QbProduct($service);
    }
}
