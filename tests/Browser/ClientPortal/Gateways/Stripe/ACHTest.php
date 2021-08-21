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

namespace Tests\Browser\ClientPortal\Gateways\Stripe;

use App\DataMapper\FeesAndLimits;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class ACHTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (static::$browsers as $browser) {
            $browser->driver->manage()->deleteAllCookies();
        }

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });

        $this->disableCompanyGateways();

        // Enable Stripe.
        CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->restore();

        // Enable ACH.
        $cg = CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->firstOrFail();
        $fees_and_limits = $cg->fees_and_limits;
        $fees_and_limits->{GatewayType::BANK_TRANSFER} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        // ACH required US to be billing country.
        $client = Client::first();
        $client->country_id = 840;
        $client->save();
    }

    public function testAddingACHAccountAndVerifyingIt()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->press('Add Payment Method')
                ->clickLink('Bank Account')
                ->type('#account-holder-name', 'John Doe')
                ->select('#country', 'US')
                ->select('#currency', 'USD')
                ->type('#routing-number', '110000000')
                ->type('#account-number', '000123456789')
                ->check('#accept-terms')
                ->press('Add Payment Method')
                ->waitForText('ACH (Verification)', 60)
                ->type('@verification-1st', '32')
                ->type('@verification-2nd', '45')
                ->press('Complete Verification')
                ->assertSee('Verification completed successfully')
                ->assertSee('Bank Transfer');
        });
    }

    public function testPayingWithExistingACH()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Bank Transfer')
                ->click('.toggle-payment-with-token')
                ->press('Pay Now')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testRemoveACHAccount()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->clickLink('View')
                ->press('Remove Payment Method')
                ->waitForText('Confirmation')
                ->click('@confirm-payment-removal')
                ->assertSee('Payment method has been successfully removed.');
        });
    }

    public function testIntegerAndMinimumValueOnVerification()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->press('Add Payment Method')
                ->clickLink('Bank Account')
                ->type('#account-holder-name', 'John Doe')
                ->select('#country', 'US')
                ->select('#currency', 'USD')
                ->type('#routing-number', '110000000')
                ->type('#account-number', '000123456789')
                ->check('#accept-terms')
                ->press('Add Payment Method')
                ->waitForText('ACH (Verification)', 60)
                ->type('@verification-1st', '0.1')
                ->type('@verification-2nd', '0')
                ->press('Complete Verification')
                ->assertSee('The transactions.0 must be an integer')
                ->assertSee('The transactions.1 must be at least 1')
                ->type('@verification-1st', '32')
                ->type('@verification-2nd', '45')
                ->press('Complete Verification')
                ->assertSee('Bank Transfer');
        });
    }
}
