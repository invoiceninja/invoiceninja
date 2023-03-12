<?php

namespace Tests\Browser\ClientPortal\Gateways\Braintree;

use App\DataMapper\FeesAndLimits;
use App\Models\Company;
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

        CompanyGateway::where('gateway_key', 'f7ec488676d310683fb51802d076d713')->restore();

        $cg = CompanyGateway::where('gateway_key', 'f7ec488676d310683fb51802d076d713')->firstOrFail();
        $fees_and_limits = $cg->fees_and_limits;
        $fees_and_limits->{GatewayType::BANK_TRANSFER} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        $company = Company::first();
        $settings = $company->settings;

        $settings->client_portal_allow_under_payment = true;
        $settings->client_portal_allow_over_payment = true;

        $company->settings = $settings;
        $company->save();
    }

    public function testAddingBankAccount()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->press('Add Payment Method')
                ->clickLink('Bank Account')
                ->type('#account-holder-name', 'John Doe')
                ->type('#account-number', '1000000000')
                ->type('#routing-number', '011000015')
                ->type('#billing-postal-code', '12345')
                ->press('Add Payment Method')
                ->waitForText('Added payment method.');
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
}
