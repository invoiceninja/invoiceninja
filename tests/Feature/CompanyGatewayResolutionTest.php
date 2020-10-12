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

use App\DataMapper\FeesAndLimits;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Listeners\Credit\CreateCreditInvitation;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;
use Illuminate\Support\Facades\Crypt;

/**
 * @test
 */
class CompanyGatewayResolutionTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $cg;

    public $cg1;

    public function setUp() :void
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
            $data[1]['fee_cap'] = 0;

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
            $data[2]['fee_cap'] = 0;

            //disable ach here 
            $json_config = json_decode(config('ninja.testvars.stripe'));
            $json_config->enable_ach = "0";

            $this->cg = new CompanyGateway;
            $this->cg->company_id = $this->company->id;
            $this->cg->user_id = $this->user->id;
            $this->cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $this->cg->require_cvv = true;
            $this->cg->show_billing_address = true;
            $this->cg->show_shipping_address = true;
            $this->cg->update_details = true;
            $this->cg->config = encrypt(json_encode($json_config));
            $this->cg->fees_and_limits = $data;
            $this->cg->save();
   

    }

    /**
     * @covers \App\Models\CompanyGateway::calcGatewayFee()
     */
    public function testGatewayResolution()
    {
        
        $fee = $this->cg->calcGatewayFee(10, false, GatewayType::CREDIT_CARD);
        $this->assertEquals(0.2, $fee);
        $fee = $this->cg->calcGatewayFee(10, false, GatewayType::BANK_TRANSFER);
        $this->assertEquals(0.1, $fee);

    }

    /**
     * @covers \App|Models\Client::validGatewayForAmount()
     */

    public function testValidationForGatewayAmount()
    {
        $this->assertTrue($this->client->validGatewayForAmount($this->cg->fees_and_limits->{1}, 10));
        $this->assertTrue($this->client->validGatewayForAmount($this->cg->fees_and_limits->{2}, 10));
    }

    public function testAvailablePaymentMethodsCount()
    {
        $amount = 10;
        $payment_methods = [];

        $this->assertInstanceOf("\\stdClass", $this->cg->fees_and_limits);
        $this->assertObjectHasAttribute('min_limit',$this->cg->fees_and_limits->{1});

        foreach ($this->cg->driver($this->client)->gatewayTypes() as $type) 
        {

            if(property_exists($this->cg->fees_and_limits, $type))
            {
                if($this->client->validGatewayForAmount($this->cg->fees_and_limits->{$type}, $amount)){
                    $payment_methods[] = [$this->cg->id => $type];
                }
            }
            else 
            {
                $payment_methods[] = [$this->cg->id => $type];
            }

        }

        $this->assertEquals(3, count($payment_methods));
    }

    public function testAddAchBackIntoMethods()
    {
        $this->assertEquals(3, count($this->cg->driver($this->client)->gatewayTypes()));

        $cg_config = json_decode(decrypt($this->cg->config));
        $cg_config->enable_ach = "1";
        $this->cg->config = encrypt(json_encode($cg_config));
        $this->cg->save();

        $this->assertEquals(4, count($this->cg->driver($this->client)->gatewayTypes()));

        info(print_r($this->client->getPaymentMethods(10),1));
    }
}
