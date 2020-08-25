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

        if (!config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }
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
        $data[1]['fee_cap'] = 0;

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

        $properties = array_keys(get_object_vars($cg->fees_and_limits));
        $fees_and_limits = $cg->fees_and_limits->{$properties[0]};

        $this->assertNotNull($fees_and_limits);


        //confirm amount filtering works
            $amount = 100;

            $this->assertFalse($this->checkSieve($cg, $amount));

            $amount = 235;

            $this->assertTrue($this->checkSieve($cg, $amount));

            $amount = 70000;

            $this->assertFalse($this->checkSieve($cg, $amount));

    }

    public function checkSieve($cg, $amount)
    {
        if(isset($cg->fees_and_limits)){
            $properties = array_keys(get_object_vars($cg->fees_and_limits));
            $fees_and_limits = $cg->fees_and_limits->{$properties[0]};
        }
        else
            $passes = true;

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !==  null && $amount < $fees_and_limits->min_limit) {
            info("amount {$amount} less than ". $fees_and_limits->min_limit);
            $passes = false;   
        }
        else if ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !==  null && $amount > $fees_and_limits->max_limit){ 
            info("amount {$amount} greater than ". $fees_and_limits->max_limit);
            $passes = false;
        }
        else
            $passes = true;

        return $passes;
    }

    public function testFeesAreAppendedToInvoice()
    {

        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 1.00;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['fee_cap'] = 0;

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

        $balance = $this->invoice->balance;

        $this->invoice = $this->invoice->service()->addGatewayFee($cg, $this->invoice->balance)->save();
        $this->invoice = $this->invoice->calc()->getInvoice();

        $items = $this->invoice->line_items;

        $this->assertEquals(($balance+1), $this->invoice->balance);
    }
}