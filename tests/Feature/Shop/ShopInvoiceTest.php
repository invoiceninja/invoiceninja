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

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\Shop\InvoiceController
 */
class ShopInvoiceTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testTokenSuccess()
    {
        $this->company->enable_shop_api = true;
        $this->company->save();

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-COMPANY-KEY' => $this->company->company_key,
            ])->get('api/v1/shop/products');
        } catch (ValidationException $e) {
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);
    }

    public function testTokenFailure()
    {
        $this->company->enable_shop_api = true;
        $this->company->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->get('/api/v1/products');

        $response->assertStatus(403);

        $arr = $response->json();
    }

    public function testCompanyEnableShopApiBooleanWorks()
    {
        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-COMPANY-KEY' => $this->company->company_key,
            ])->get('api/v1/shop/products');
        } catch (ValidationException $e) {
            $this->assertNotNull($message);
        }

        $response->assertStatus(403);
    }

    public function testGetByProductKey()
    {
        $this->company->enable_shop_api = true;
        $this->company->save();

        Product::truncate();

        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->get('/api/v1/shop/product/'.$product->product_key);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($product->hashed_id, $arr['data']['id']);
    }

    public function testGetByClientByContactKey()
    {
        $this->company->enable_shop_api = true;
        $this->company->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->get('/api/v1/shop/client/'.$this->client->contacts->first()->contact_key);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($this->client->hashed_id, $arr['data']['id']);
    }

    public function testCreateClientOnShopRoute()
    {
        $this->company->enable_shop_api = true;
        $this->company->save();

        $data = [
            'name' => 'ShopClient',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->postJson('/api/v1/shop/clients/', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('ShopClient', $arr['data']['name']);
    }

    public function testCreateInvoiceOnShopRoute()
    {
        $this->company->enable_shop_api = true;
        $this->company->save();

        $data = [
            'name' => 'ShopClient',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->postJson('/api/v1/shop/clients/', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $client_hashed_id = $arr['data']['id'];

        $invoice_data = [
            'client_id' => $client_hashed_id,
            'po_number' => 'shop_order',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->postJson('/api/v1/shop/invoices/', $invoice_data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('shop_order', $arr['data']['po_number']);
    }
}
