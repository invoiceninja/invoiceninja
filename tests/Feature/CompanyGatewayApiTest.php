<?php

namespace Tests\Feature;

use App\DataMapper\DefaultSettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
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
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class CompanyGatewayApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;


    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

    }


    public function testCompanyGatewayEndPoints()
    {
        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
        ];

        /* POST */
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->post('/api/v1/company_gateways', $data);

        $cg = $response->json();

        $cg_id = $cg['data']['id'];

        $this->assertNotNull($cg_id);

        $response->assertStatus(200);



        /* GET */
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->get("/api/v1/company_gateways/{$cg_id}");


        $response->assertStatus(200);


        /* GET CREATE */
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->get('/api/v1/company_gateways/create');


        $response->assertStatus(200);

        /* PUT */
        $data = [
            'config' => 'changed',
        ];

        \Log::error('the id = '.$cg_id);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->put("/api/v1/company_gateways/{$cg_id}", $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->put("/api/v1/company_gateways/{$cg_id}", $data);


        $response->assertStatus(200);
      

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->delete("/api/v1/company_gateways/{$cg_id}", $data);


        $response->assertStatus(200);
      


    }
    
}