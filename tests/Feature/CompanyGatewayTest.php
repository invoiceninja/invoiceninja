<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature;

use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

        if (! config('ninja.testvars.stripe')) {
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
        $data[1]['adjust_fee_percent'] = true;
        $data[1]['fee_cap'] = 0;

        $cg = new CompanyGateway;
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
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
        if (isset($cg->fees_and_limits)) {
            $properties = array_keys(get_object_vars($cg->fees_and_limits));
            $fees_and_limits = $cg->fees_and_limits->{$properties[0]};
        } else {
            $passes = true;
        }

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !== null && $amount < $fees_and_limits->min_limit) {
            info("amount {$amount} less than ".$fees_and_limits->min_limit);
            $passes = false;
        } elseif ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !== null && $amount > $fees_and_limits->max_limit) {
            info("amount {$amount} greater than ".$fees_and_limits->max_limit);
            $passes = false;
        } else {
            $passes = true;
        }

        return $passes;
    }

    public function testFeesAreAppendedToInvoice() //after refactor this may be redundant
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
        $data[1]['adjust_fee_percent'] = true;
        $data[1]['fee_cap'] = 0;

        $cg = new CompanyGateway;
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $balance = $this->invoice->balance;

        $this->invoice = $this->invoice->service()->addGatewayFee($cg, GatewayType::CREDIT_CARD, $this->invoice->balance)->save();
        $this->invoice = $this->invoice->calc()->getInvoice();

        $items = $this->invoice->line_items;

        $this->assertEquals(($balance + 1), $this->invoice->balance);
    }

    public function testProRataGatewayFees()
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 1.00;
        $data[1]['fee_percent'] = 2;
        $data[1]['fee_tax_name1'] = 'GST';
        $data[1]['fee_tax_rate1'] = 10;
        $data[1]['fee_tax_name2'] = 'GST';
        $data[1]['fee_tax_rate2'] = 10;
        $data[1]['fee_tax_name3'] = 'GST';
        $data[1]['fee_tax_rate3'] = 10;
        $data[1]['adjust_fee_percent'] = true;
        $data[1]['fee_cap'] = 0;

        $cg = new CompanyGateway;
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $total = 10.93;
        $total_invoice_count = 5;
        $total_gateway_fee = round($cg->calcGatewayFee($total, true, GatewayType::CREDIT_CARD), 2);

        $this->assertEquals(1.58, $total_gateway_fee);

        /*simple pro rata*/
        $fees_and_limits = $cg->getFeesAndLimits(GatewayType::CREDIT_CARD);

        /*Calculate all subcomponents of the fee*/

        // $fee_component_amount  = $fees_and_limits->fee_amount ?: 0;
        // $fee_component_percent = $fees_and_limits->fee_percent ? ($total * $fees_and_limits->fee_percent / 100) : 0;

        // $combined_fee_component = $fee_component_amount + $fee_component_percent;

        // $fee_component_tax_name1 = $fees_and_limits->fee_tax_name1 ?: '';
        // $fee_component_tax_rate1 = $fees_and_limits->fee_tax_rate1 ? ($combined_fee_component * $fees_and_limits->fee_tax_rate1 / 100) : 0;

        // $fee_component_tax_name2 = $fees_and_limits->fee_tax_name2 ?: '';
        // $fee_component_tax_rate2 = $fees_and_limits->fee_tax_rate2 ? ($combined_fee_component * $fees_and_limits->fee_tax_rate2 / 100) : 0;

        // $fee_component_tax_name3 = $fees_and_limits->fee_tax_name3 ?: '';
        // $fee_component_tax_rate3 = $fees_and_limits->fee_tax_rate3 ? ($combined_fee_component * $fees_and_limits->fee_tax_rate3 / 100) : 0;

        // $pro_rata_fee = round($total_gateway_fee / $total_invoice_count,2);

        // while($pro_rata_fee * $total_invoice_count != $total_gateway_fee) {

        //     //nudge one pro rata fee until we get the desired amount
        //     $sub_total_fees = ($pro_rata_fee*($total_invoice_count--));

        //     //work out if we have to nudge up or down

        //     if($pro_rata_fee*$total_invoice_count  > $total_gateway_fee) {
        //         //nudge DOWN
        //         $pro_rata_fee - 0.01; //this will break if the currency doesn't have decimals
        //     }
        //     else {
        //         //nudge UP
        //     }

        // }

        // $this->assertEquals(1.56, $pro_rata_fee*$total_invoice_count);
    }
}
