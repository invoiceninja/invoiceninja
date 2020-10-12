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

/**
 * @test
 */
class CompanyGatewayResolutionTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

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
            $data[1]['fee_amount'] = 1.00;
            $data[1]['fee_percent'] = 2;
            $data[1]['fee_tax_name1'] = 'GST';
            $data[1]['fee_tax_rate1'] = 10;
            $data[1]['fee_tax_name2'] = 'GST';
            $data[1]['fee_tax_rate2'] = 10;
            $data[1]['fee_tax_name3'] = 'GST';
            $data[1]['fee_tax_rate3'] = 10;
            $data[1]['fee_cap'] = 0;

            $json_config = config('ninja.testvars.stripe');
            $json_config->enable_ach = "0";

            //disable ach here 
            $cg = new CompanyGateway;
            $cg->company_id = $this->company->id;
            $cg->user_id = $this->user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->show_billing_address = true;
            $cg->show_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(json_encode($json_config));
            $cg->fees_and_limits = $data;
            $cg->save();
   
            $data = [];
            $data[2]['min_limit'] = -1;
            $data[2]['max_limit'] = -1;
            $data[2]['fee_amount'] = 1.00;
            $data[2]['fee_percent'] = 1;
            $data[2]['fee_tax_name1'] = 'GST';
            $data[2]['fee_tax_rate1'] = 10;
            $data[2]['fee_tax_name2'] = 'GST';
            $data[2]['fee_tax_rate2'] = 10;
            $data[2]['fee_tax_name3'] = 'GST';
            $data[2]['fee_tax_rate3'] = 10;
            $data[2]['fee_cap'] = 0;

            //ensable ach here 
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
    }

    public function testGatewayResolution()
    {
        $this->assertTrue(true);

        //i want to test here resolution of bank_transfers inside and outside of fees and limits.
    }
}
