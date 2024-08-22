<?php

namespace Tests\Feature\Import\Quickbooks;

use Tests\TestCase;
use App\Import\Providers\Quickbooks;
use App\Import\Transformer\BaseTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Illuminate\Support\Facades\Cache;
use Mockery;
use App\Models\Client;
use App\Models\Product;
use App\Models\Invoice;
use Illuminate\Support\Str;
use ReflectionClass;
use Illuminate\Support\Facades\Auth;

class QuickbooksTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected $quickbooks;
    protected $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped("NO BUENO");
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['database.default' => config('ninja.db.default')]);
        $this->makeTestData();
        //
        $this->withoutExceptionHandling();
        Auth::setUser($this->user);

    }

    public function testImportCallsGetDataOnceForClient()
    {
        $data = (json_decode(file_get_contents(base_path('tests/Feature/Import/customers.json')), true))['Customer'];
        $hash = Str::random(32);
        Cache::put($hash.'-client', base64_encode(json_encode($data)), 360);

        $quickbooks = Mockery::mock(Quickbooks::class, [[
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => []]],
            'skip_header' => true,
            'import_type' => 'quickbooks',
        ], $this->company ])->makePartial();
        $quickbooks->shouldReceive('getData')
            ->once()
            ->with('client')
            ->andReturn($data);

        // Mocking the dependencies used within the client method

        $quickbooks->import('client');

        $this->assertArrayHasKey('clients', $quickbooks->entity_count);
        $this->assertGreaterThan(0, $quickbooks->entity_count['clients']);

        $base_transformer = new BaseTransformer($this->company);
        $this->assertTrue($base_transformer->hasClient('Sonnenschein Family Store'));
        $contact = $base_transformer->getClient('Amy\'s Bird Sanctuary', '');
        $contact = Client::where('name', 'Amy\'s Bird Sanctuary')->first();
        $this->assertEquals('(650) 555-3311', $contact->phone);
        $this->assertEquals('Birds@Intuit.com', $contact->contacts()->first()->email);
    }

    public function testImportCallsGetDataOnceForProducts()
    {
        $data = (json_decode(file_get_contents(base_path('tests/Feature/Import/items.json')), true))['Item'];
        $hash = Str::random(32);
        Cache::put($hash.'-item', base64_encode(json_encode($data)), 360);

        $quickbooks = Mockery::mock(Quickbooks::class, [[
            'hash' => $hash,
            'column_map' => ['item' => ['mapping' => []]],
            'skip_header' => true,
            'import_type' => 'quickbooks',
        ], $this->company ])->makePartial();
        $quickbooks->shouldReceive('getData')
            ->once()
            ->with('product')
            ->andReturn($data);

        // Mocking the dependencies used within the client method

        $quickbooks->import('product');

        $this->assertArrayHasKey('products', $quickbooks->entity_count);
        $this->assertGreaterThan(0, $quickbooks->entity_count['products']);

        $base_transformer = new BaseTransformer($this->company);
        $this->assertTrue($base_transformer->hasProduct('Gardening'));
        $product = Product::where('product_key', 'Pest Control')->first();
        $this->assertGreaterThanOrEqual(35, $product->price);
        $this->assertLessThanOrEqual(0, $product->quantity);
    }

    public function testImportCallsGetDataOnceForInvoices()
    {
        $data = (json_decode(file_get_contents(base_path('tests/Feature/Import/invoices.json')), true))['Invoice'];
        $hash = Str::random(32);
        Cache::put($hash.'-invoice', base64_encode(json_encode($data)), 360);
        $quickbooks = Mockery::mock(Quickbooks::class, [[
            'hash' => $hash,
            'column_map' => ['invoice' => ['mapping' => []]],
            'skip_header' => true,
            'import_type' => 'quickbooks',
        ], $this->company ])->makePartial();
        $quickbooks->shouldReceive('getData')
            ->once()
            ->with('invoice')
            ->andReturn($data);
        $quickbooks->import('invoice');
        $this->assertArrayHasKey('invoices', $quickbooks->entity_count);
        $this->assertGreaterThan(0, $quickbooks->entity_count['invoices']);
        $base_transformer = new BaseTransformer($this->company);
        $this->assertTrue($base_transformer->hasInvoice(1007));
        $invoice = Invoice::where('number', 1012)->first();
        $data = collect($data)->where('DocNumber', '1012')->first();
        $this->assertGreaterThanOrEqual($data['TotalAmt'], $invoice->amount);
        $this->assertEquals(count($data['Line']) - 1, count((array)$invoice->line_items));
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
