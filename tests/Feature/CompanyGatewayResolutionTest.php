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

use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\GatewayType;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class CompanyGatewayResolutionTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $cg;

    public $cg1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        if (! config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();

        CompanyGateway::query()->withTrashed()->cursor()->each(function ($cg) {
            $cg->forceDelete();
        });

        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 0.00;
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

        $data[2]['min_limit'] = -1;
        $data[2]['max_limit'] = -1;
        $data[2]['fee_amount'] = 0.00;
        $data[2]['fee_percent'] = 1;
        $data[2]['fee_tax_name1'] = 'GST';
        $data[2]['fee_tax_rate1'] = 10;
        $data[2]['fee_tax_name2'] = 'GST';
        $data[2]['fee_tax_rate2'] = 10;
        $data[2]['fee_tax_name3'] = 'GST';
        $data[2]['fee_tax_rate3'] = 10;
        $data[2]['adjust_fee_percent'] = false;
        $data[2]['fee_cap'] = 0;
        $data[2]['is_enabled'] = true;

        //disable ach here
        $json_config = json_decode(config('ninja.testvars.stripe'));

        $this->cg = new CompanyGateway();
        $this->cg->company_id = $this->company->id;
        $this->cg->user_id = $this->user->id;
        $this->cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $this->cg->require_cvv = true;
        $this->cg->require_billing_address = true;
        $this->cg->require_shipping_address = true;
        $this->cg->update_details = true;
        $this->cg->config = encrypt(json_encode($json_config));
        $this->cg->fees_and_limits = $data;
        $this->cg->save();
    }

    /**
     *  \App\Models\CompanyGateway::calcGatewayFee()
     */
    public function testGatewayResolution()
    {
        $fee = $this->cg->calcGatewayFee(10, GatewayType::CREDIT_CARD, false);
        $this->assertEquals(0.2, $fee);
    }

    /**
     *  \App|Models\Client::validGatewayForAmount()
     */
    public function testValidationForGatewayAmount()
    {
        $this->assertTrue($this->client->validGatewayForAmount($this->cg->fees_and_limits->{1}, 10));
        $this->assertTrue($this->client->validGatewayForAmount($this->cg->fees_and_limits->{2}, 10));
    }

    public function testAvailablePaymentMethodsCount()
    {
        $amount = 10;

        $this->client->country_id = 840;
        $this->client->save();

        Credit::query()->withTrashed()->cursor()->each(function ($c) {
            $c->forceDelete();
        });

        $this->assertInstanceOf('\\stdClass', $this->cg->fees_and_limits);
        $this->assertNotNull($this->cg->fees_and_limits->{1}->min_limit);
        $payment_methods = $this->client->service()->getPaymentMethods($amount);


        $this->assertEquals(2, count($payment_methods));
    }

    public function testRemoveMethods()
    {
        $amount = 10;

        CompanyGateway::query()->withTrashed()->cursor()->each(function ($cg) {
            $cg->forceDelete();
        });

        Credit::query()->withTrashed()->cursor()->each(function ($c) {
            $c->forceDelete();
        });

        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 0.00;
        $data[1]['fee_percent'] = 2;
        $data[1]['fee_tax_name1'] = 'GST';
        $data[1]['fee_tax_rate1'] = 10;
        $data[1]['fee_tax_name2'] = 'GST';
        $data[1]['fee_tax_rate2'] = 10;
        $data[1]['fee_tax_name3'] = 'GST';
        $data[1]['fee_tax_rate3'] = 10;
        $data[1]['adjust_fee_percent'] = true;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $data[2]['min_limit'] = -1;
        $data[2]['max_limit'] = -1;
        $data[2]['fee_amount'] = 0.00;
        $data[2]['fee_percent'] = 1;
        $data[2]['fee_tax_name1'] = 'GST';
        $data[2]['fee_tax_rate1'] = 10;
        $data[2]['fee_tax_name2'] = 'GST';
        $data[2]['fee_tax_rate2'] = 10;
        $data[2]['fee_tax_name3'] = 'GST';
        $data[2]['fee_tax_rate3'] = 10;
        $data[2]['adjust_fee_percent'] = true;
        $data[2]['fee_cap'] = 0;
        $data[2]['is_enabled'] = false;

        //disable ach here
        $json_config = json_decode(config('ninja.testvars.stripe'));

        $this->cg = new CompanyGateway();
        $this->cg->company_id = $this->company->id;
        $this->cg->user_id = $this->user->id;
        $this->cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $this->cg->require_cvv = true;
        $this->cg->require_billing_address = true;
        $this->cg->require_shipping_address = true;
        $this->cg->update_details = true;
        $this->cg->config = encrypt(json_encode($json_config));
        $this->cg->fees_and_limits = $data;
        $this->cg->save();

        $this->client->country_id = 840;
        $this->client->save();

        $this->assertEquals(1, count($this->client->service()->getPaymentMethods($amount)));
    }

    public function testEnableFeeAdjustment()
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 0.3;
        $data[1]['fee_percent'] = 1.75;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = true;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $data[2]['min_limit'] = -1;
        $data[2]['max_limit'] = -1;
        $data[2]['fee_amount'] = 0.30;
        $data[2]['fee_percent'] = 1.75;
        $data[2]['fee_tax_name1'] = '';
        $data[2]['fee_tax_rate1'] = 0;
        $data[2]['fee_tax_name2'] = '';
        $data[2]['fee_tax_rate2'] = 0;
        $data[2]['fee_tax_name3'] = '';
        $data[2]['fee_tax_rate3'] = 0;
        $data[2]['adjust_fee_percent'] = true;
        $data[2]['fee_cap'] = 0;
        $data[2]['is_enabled'] = true;

        //disable ach here
        $json_config = json_decode(config('ninja.testvars.stripe'));

        $this->cg = new CompanyGateway();
        $this->cg->company_id = $this->company->id;
        $this->cg->user_id = $this->user->id;
        $this->cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $this->cg->require_cvv = true;
        $this->cg->require_billing_address = true;
        $this->cg->require_shipping_address = true;
        $this->cg->update_details = true;
        $this->cg->config = encrypt(json_encode($json_config));
        $this->cg->fees_and_limits = $data;
        $this->cg->save();

        $fee = $this->cg->calcGatewayFee(89, GatewayType::CREDIT_CARD, false);
        $this->assertEquals(1.89, round($fee, 2));
    }
}
