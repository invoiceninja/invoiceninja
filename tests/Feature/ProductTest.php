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

namespace Tests\Feature;

use App\DataMapper\InvoiceItem;
use Tests\TestCase;
use App\Models\Invoice;
use App\Models\Product;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers App\Http\Controllers\ProductController
 */
class ProductTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
        $this->withoutExceptionHandling();

    }

    public function testProductCostMigration()
    {
        $items = [];

        $item = new InvoiceItem();
        $item->product_cost = 0;
        $item->product_key = 'test';
        $item->quantity = 1;
        $item->cost = 10;
        $item->notes = 'product';

        $items[] = $item;

        $p = Product::factory()
            ->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'product_key' => 'test',
                'cost' => 10,
                'price' => 20,
                'quantity' => 1,
                'notes' => 'product',
            ]);

        $i = Invoice::factory()
                ->create([
                    'client_id' => $this->client->id,
                    'company_id' => $this->company->id,
                    'user_id' => $this->user->id,
                    'line_items' => $items,
                ]);


        $line_items = $i->line_items;

        $this->assertEquals(0, $line_items[0]->product_cost);

        Invoice::withTrashed()
            ->where('is_deleted', false)
            ->cursor()
            ->each(function (Invoice $invoice) {

                $line_items = $invoice->line_items;

                foreach ($line_items as $key => $item) {

                    if($product = Product::where('company_id', $invoice->company_id)->where('product_key', $item->product_key)->where('cost', '>', 0)->first()) {
                        if((property_exists($item, 'product_cost') && $item->product_cost == 0) || !property_exists($item, 'product_cost')) {
                            $line_items[$key]->product_cost = $product->cost;
                        }
                    }

                }

                $invoice->line_items = $line_items;
                $invoice->saveQuietly();


            });


        $i = $i->fresh();
        $line_items = $i->line_items;

        $this->assertEquals(10, $line_items[0]->product_cost);


    }

    public function testSetTaxId()
    {
        $p = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id
        ]);


        $this->assertEquals(1, $p->tax_id);

        $update = [
            'ids' => [$p->hashed_id],
            'action' => 'set_tax_id',
            'tax_id' => 6,
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/products/bulk', $update)
            ->assertStatus(200);
        } catch(\Exception $e) {

        }

        $p = $p->fresh();

        $this->assertEquals(6, $p->tax_id);

    }

    public function testProductGetProductKeyFilter()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/products?product_key=xx')
        ->assertStatus(200);
    }

    public function testProductList()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/products');

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post(
            '/api/v1/products/',
            [
                'product_key' => 'a-new-product-key',
                'notes' => 'Product Notes',
                'cost' => 10,
                'qty' => 10,
                'tax_name1' => 'GST',
                'tax_rate1' => 10,
                'tax_name2' => 'VAT',
                'tax_rate2' => 17.5,
                'custom_value1' => 'custom',
                'custom_value2' => 'custom',
                'custom_value3' => 'custom',
                'custom_value4' => 'custom',
                'is_deleted' => 0,
            ]
        )
            ->assertStatus(200);

        $arr = $response->json();
        $product = Product::find($this->decodePrimaryKey($arr['data']['id']));

        $product_update = [
            'notes' => 'CHANGE',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/products/'.$this->encodePrimaryKey($product->id), $product_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/products/'.$this->encodePrimaryKey($product->id))
        ->assertStatus(200);
    }
}
