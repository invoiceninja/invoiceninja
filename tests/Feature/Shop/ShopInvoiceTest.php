<?php

namespace Tests\Feature\Shop;

use App\Factory\CompanyUserFactory;
use App\Models\CompanyToken;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
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

    public function setUp() :void
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

    public function testTokenFailure()
    {

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-COMPANY-KEY' => $this->company->company_key
            ])->get('/api/v1/shop/products');


        $response->assertStatus(200);
    }

    public function testTokenSuccess()
    {

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-COMPANY-KEY' => $this->company->company_key
            ])->get('/api/v1/products');


        $response->assertStatus(403);

        $arr = $response->json();
    }

    public function testGetByProductKey()
    {
        $product = factory(\App\Models\Product::class)->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
            ]);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-COMPANY-KEY' => $this->company->company_key
            ])->get('/api/v1/shop/product/'.$product->product_key);


        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($product->hashed_id, $arr['data']['id']);
    }

    public function testGetByClientByContactKey()
    {

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-COMPANY-KEY' => $this->company->company_key
            ])->get('/api/v1/shop/client/'.$this->client->contacts->first()->contact_key);


        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($this->client->hashed_id, $arr['data']['id']);

    }
}
