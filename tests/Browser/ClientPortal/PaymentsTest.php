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

namespace Tests\Browser\ClientPortal;

use App\Models\Client;
use App\Models\CompanyGateway;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class PaymentsTest extends DuskTestCase
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
    }

    public function testPageLoads()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payments.index')
                ->assertSeeIn('span[data-ref="meta-title"]', 'Payments')
                ->visitRoute('client.logout');
        });
    }

    public function testRequiredFieldsCheck()
    {
        $this->disableCompanyGateways();

        // Enable Stripe.
        CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->restore();

        // Stripe requires post code.
        Client::first()->update(['postal_code' => null]);

        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->assertSee('Postal Code')
                ->type('client_postal_code', 10000)
                ->press('Continue')
                ->pause(2000)
                ->type('#cardholder-name', 'John Doe')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('cardnumber', '4242 4242 4242 4242')
                        ->type('exp-date', '04/22')
                        ->type('cvc', '242');
                })
                ->click('#pay-now')
                ->waitForText('Details of the payment', 60)
                ->visitRoute('client.logout');
        });
    }
}
