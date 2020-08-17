<?php

namespace Tests\Feature;

use App\DataMapper\DefaultSettings;
use App\DataMapper\FeesAndLimits;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\User;
use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use PaymentLibrariesSeeder;
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
    use CompanyGatewayFeesAndLimitsSaver;

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

        /* PUT */
        $data = [
            'config' => 'changed',
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->put("/api/v1/company_gateways/".$cg_id, $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->delete("/api/v1/company_gateways/{$cg_id}", $data);

        $response->assertStatus(200);

    }
    

    public function testCompanyGatewayFeesAndLimitsSuccess()
    {

        $fee = new FeesAndLimits;

        $fee = (array)$fee;

        $fee_and_limit['1'] = ['min_limit' => 1];
        $fee_and_limit['2'] = ['min_limit' => 1];
        $fee_and_limit['3'] = ['min_limit' => 1];
        $fee_and_limit['4'] = ['min_limit' => 1];
        $fee_and_limit['5'] = ['min_limit' => 1];

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_and_limit,
        ];

        /* POST */
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->post('/api/v1/company_gateways', $data);

        $cg = $response->json();

        $cg_id = $cg['data']['id'];

        $this->assertNotNull($cg_id);

        $cg_fee = $cg['data']['fees_and_limits'];

        $this->assertNotNull($cg_fee);

        $response->assertStatus(200);


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->get('/api/v1/company_gateways/'.$this->encodePrimaryKey($cg['data']['id']));

        $cg = $response->json();

        $response->assertStatus(200);
    }


    public function testCompanyGatewayFeesAndLimitsFails()
    {

        $fee_and_limit['bank_transfer'] = new FeesAndLimits;

        $fee_and_limit['bank_transfer']->adjust_fee_percent = 10;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_and_limit,
        ];

        /* POST */
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->post('/api/v1/company_gateways', $data);


        $response->assertStatus(200);
    }

    public function testCompanyGatewayArrayBuilder()
    {

        $arr = [
            'min_limit' => 1,
            'max_limit' => 2
        ];

        $fal = (array)new FeesAndLimits;

        $new_arr = array_replace($fal, $arr);

        $this->assertEquals($arr['min_limit'], $new_arr['min_limit']);
        $this->assertTrue(array_key_exists('fee_amount', $new_arr));
    }

    public function testFeesAndLimitsFeeAmountCalcuation()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        $fee->fee_amount = 10;
        // $fee->fee_percent = 2;
        // $fee->fee_tax_name1 = 'GST';
        // $fee->fee_tax_rate1 = '10.0';

        $fee_arr[1] = (array)$fee; 

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->post('/api/v1/company_gateways', $data);


        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(10, $company_gateway->calcGatewayFee(10));
    }

    public function testFeesAndLimitsFeeAmountCalcuation()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        //$fee->fee_amount = 10;
         $fee->fee_percent = 2;
        // $fee->fee_tax_name1 = 'GST';
        // $fee->fee_tax_rate1 = '10.0';

        $fee_arr[1] = (array)$fee; 

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->post('/api/v1/company_gateways', $data);


        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(10, $company_gateway->calcGatewayFee(10));
    }
}
