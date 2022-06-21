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

use App\DataMapper\FeesAndLimits;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Models\CompanyGateway
 */
class CompanyGatewayApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    use CompanyGatewayFeesAndLimitsSaver;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testCompanyGatewayEndPointsWithIncorrectFields()
    {
        $data = [
            'config' => 'random config',
            'gateway_key' => '',
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(302);
    }

    public function testCompanyGatewayEndPointsWithInvalidFields()
    {
        $data = [
            'config' => 'random config',
            'gateway_key' => '$#%^&*(',
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(302);
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
            'X-API-TOKEN' => $this->token,
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
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/company_gateways/'.$cg_id, $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete("/api/v1/company_gateways/{$cg_id}", $data);

        $response->assertStatus(200);
    }

    public function testCompanyGatewayFeesAndLimitsSuccess()
    {
        $fee = new FeesAndLimits;

        $fee = (array) $fee;

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
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $cg = $response->json();

        $cg_id = $cg['data']['id'];

        $this->assertNotNull($cg_id);

        $cg_fee = $cg['data']['fees_and_limits'];

        $this->assertNotNull($cg_fee);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/company_gateways/'.$this->encodePrimaryKey($cg['data']['id']));

        $cg = $response->json();

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/company_gateways');

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/company_gateways?filter=x');

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
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);
    }

    public function testCompanyGatewayArrayBuilder()
    {
        $arr = [
            'min_limit' => 1,
            'max_limit' => 2,
        ];

        $fal = (array) new FeesAndLimits;

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

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(10, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD));
    }

    public function testFeesAndLimitsFeePercentCalcuation()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        //$fee->fee_amount = 10;
        $fee->fee_percent = 2;
        // $fee->fee_tax_name1 = 'GST';
        // $fee->fee_tax_rate1 = '10.0';

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(0.2, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD));
    }

    public function testFeesAndLimitsFeePercentAndAmountCalcuation()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        $fee->fee_amount = 10;
        $fee->fee_percent = 2;
        // $fee->fee_tax_name1 = 'GST';
        // $fee->fee_tax_rate1 = '10.0';

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(10.2, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD));
    }

    public function testFeesAndLimitsFeePercentAndAmountCalcuationOneHundredPercent()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        $fee->fee_amount = 0;
        $fee->fee_percent = 100;
        $fee->adjust_fee_percent = false;
        // $fee->fee_tax_name1 = 'GST';
        // $fee->fee_tax_rate1 = '10.0';

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(10, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD));
    }

    public function testFeesAndLimitsFeePercentAndAmountCalcuationOneHundredPercentVariationOne()
    {
        $fee = new FeesAndLimits;
        $fee->fee_amount = 0;
        $fee->fee_percent = 10;

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(1, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD));
    }

    public function testFeesAndLimitsFeePercentAndAmountAndTaxCalcuation()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        $fee->fee_amount = 10;
        // $fee->fee_percent = 2;
        $fee->fee_tax_name1 = 'GST';
        $fee->fee_tax_rate1 = '10.0';

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(11, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD, true));
    }

    public function testFeesAndLimitsFeePercentAndAmountAndTaxCalcuationInclusiveTaxes()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        $fee->fee_amount = 10;
        // $fee->fee_percent = 2;
        $fee->fee_tax_name1 = 'GST';
        $fee->fee_tax_rate1 = '10.0';

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(10, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD));
    }

    public function testFeesAndLimitsFeePercentAndAmountAndDoubleTaxCalcuation()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        $fee->fee_amount = 10;
        // $fee->fee_percent = 2;
        $fee->fee_tax_name1 = 'GST';
        $fee->fee_tax_rate1 = '10.0';
        $fee->fee_tax_name2 = 'GST';
        $fee->fee_tax_rate2 = '10.0';

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(12, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD, true));
    }

    public function testFeesAndLimitsFeePercentAndAmountAndDoubleTaxCalcuationWithFeeCap()
    {
        //{"1":{"min_limit":1,"max_limit":1000000,"fee_amount":10,"fee_percent":2,"fee_tax_name1":"","fee_tax_name2":"","fee_tax_name3":"","fee_tax_rate1":0,"fee_tax_rate2":0,"fee_tax_rate3":0,"fee_cap":10,"adjust_fee_percent":true}}
        $fee = new FeesAndLimits;
        $fee->fee_amount = 10;
        // $fee->fee_percent = 2;
        $fee->fee_tax_name1 = 'GST';
        $fee->fee_tax_rate1 = '10.0';
        $fee->fee_tax_name2 = 'GST';
        $fee->fee_tax_rate2 = '10.0';
        $fee->fee_cap = 1;

        $fee_arr[1] = (array) $fee;

        $data = [
            'config' => 'random config',
            'gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb',
            'fees_and_limits' => $fee_arr,
        ];

        /* POST */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/company_gateways', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $this->decodePrimaryKey($arr['data']['id']);

        $company_gateway = CompanyGateway::find($id);

        $this->assertEquals(1.2, $company_gateway->calcGatewayFee(10, GatewayType::CREDIT_CARD, true));
    }
}
