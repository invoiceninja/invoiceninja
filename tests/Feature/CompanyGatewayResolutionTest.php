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

            $cg = new CompanyGateway;
            $cg->company_id = $this->company->id;
            $cg->user_id = $this->user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->show_billing_address = true;
            $cg->show_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();
    }

    public function testGatewayResolution()
    {
        $this->assertTrue(true);
    }
}
