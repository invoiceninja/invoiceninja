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

use App\Models\Product;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ProductController
 */
class ProductTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
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
