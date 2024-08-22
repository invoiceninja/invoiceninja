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

use App\Jobs\Invoice\CheckGatewayFee;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    // use RefreshDatabase;

    protected function setUp(): void
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

    public function testSetConfigFields()
    {
        $company_gateway = CompanyGateway::first();

        $this->assertNotNull($company_gateway->getConfig());

        $company_gateway->setConfigField('test', 'test');

        $this->assertEquals('test', $company_gateway->getConfigField('test'));

        $company_gateway->setConfigField('signatureKey', 'hero');

        $this->assertEquals('hero', $company_gateway->getConfigField('signatureKey'));

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
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
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
            nlog("amount {$amount} less than ".$fees_and_limits->min_limit);
            $passes = false;
        } elseif ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !== null && $amount > $fees_and_limits->max_limit) {
            nlog("amount {$amount} greater than ".$fees_and_limits->max_limit);
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
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
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

    public function testGatewayFeesAreClearedAppropriately()
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
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
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
        $wiped_balance = $balance;

        $this->invoice = $this->invoice->service()->addGatewayFee($cg, GatewayType::CREDIT_CARD, $this->invoice->balance)->save();
        $this->invoice = $this->invoice->calc()->getInvoice();

        $items = $this->invoice->line_items;

        $this->assertEquals(($balance + 1), $this->invoice->balance);

        (new CheckGatewayFee($this->invoice->id, $this->company->db))->handle();

        $i = Invoice::withTrashed()->find($this->invoice->id);

        $this->assertEquals($wiped_balance, $i->balance);
    }

    public function testMarkPaidAdjustsGatewayFeeAppropriately()
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
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
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
        $wiped_balance = $balance;

        $this->invoice = $this->invoice->service()->addGatewayFee($cg, GatewayType::CREDIT_CARD, $this->invoice->balance)->save();
        $this->invoice = $this->invoice->calc()->getInvoice();

        $items = $this->invoice->line_items;

        $this->assertEquals(($balance + 1), $this->invoice->balance);

        $this->invoice->service()->markPaid()->save();

        $i = Invoice::withTrashed()->find($this->invoice->id);

        $this->assertEquals($wiped_balance, $i->amount);
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
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
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
        $total_gateway_fee = round($cg->calcGatewayFee($total, GatewayType::CREDIT_CARD, true), 2);

        $this->assertEquals(1.58, $total_gateway_fee);

        /*simple pro rata*/
        $fees_and_limits = $cg->getFeesAndLimits(GatewayType::CREDIT_CARD);
    }
}
