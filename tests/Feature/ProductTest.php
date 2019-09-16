<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ProductController
 */
class ProductTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

    }

    public function testProductList()
    {

        $data = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
             'name' => $this->faker->company,
          'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup', $data);


        $response->assertStatus(200);

        $acc = $response->json();

        $account = Account::find($this->decodePrimaryKey($acc['data'][0]['account']['id']));                

        $token = $account->default_company->tokens->first()->token;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/products');

        $response->assertStatus(200);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->post('/api/v1/products/', 
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
                'is_deleted' => 0
            ]
        )
            ->assertStatus(200);

        $product = Product::all()->first();

        $product_update = [
            'notes' => 'CHANGE'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->put('/api/v1/products/'.$this->encodePrimaryKey($product->id), $product_update)
            ->assertStatus(200);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->delete('/api/v1/products/'.$this->encodePrimaryKey($product->id))
        ->assertStatus(200);
    }
}
