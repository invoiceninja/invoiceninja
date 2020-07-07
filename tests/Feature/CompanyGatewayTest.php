<?php

namespace Tests\Feature;

use App\Models\CompanyGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Models\CompanyGateway
 */
class CompanyGatewayTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();
        
        $this->makeTestData();


    }

    public function testGatewayExists()
    {

        $company_gateway = CompanyGateway::first();
        $this->assertNotNull($company_gateway);

    }

    public function testFeesAndLimitsExists()
    {
        $data = [];
        $data[1]['min_limit'] = 234;
        $data[1]['max_limit'] = 65317;
        $data[1]['fee_amount'] = 0.00;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = '';
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = '';
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;

        $cg = new CompanyGateway;
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->show_billing_address = true;
        $cg->show_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $this->assertNotNull($cg->fees_and_limits);
        $this->assertNotNull($cg->fees_and_limits->{"1"});


        //confirm amount filtering works
    }
}