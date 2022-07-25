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

class SEPATest extends DuskTestCase
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

        // Enable SEPA.
        $cg = CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->firstOrFail();
        $fees_and_limits = $cg->fees_and_limits;
        $fees_and_limits->{GatewayType::SEPA} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        // SEPA required DE to be billing country.
        $client = Client::first();
        $client->country_id = 276;

        $settings = $client->settings;
        $settings->currency_id = '3';

        $client->settings = $settings;
        $client->save();
    }

    public function testPayingWithNewSEPABankAccount(): void
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->click('@pay-now-dropdown')
                ->clickLink('SEPA Direct Debit')
                ->type('#sepa-name', 'John Doe')
                ->type('#sepa-email-address', 'test@invoiceninja.com')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser->type('iban', 'DE89370400440532013000');
                })
                ->check('#sepa-mandate-acceptance', true)
                ->click('#pay-now')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testPayingWithNewSEPABankAccountAndSaveForFuture(): void
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->click('@pay-now-dropdown')
                ->clickLink('SEPA Direct Debit')
                ->type('#sepa-name', 'John Doe')
                ->type('#sepa-email-address', 'test@invoiceninja.com')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser->type('iban', 'DE89370400440532013000');
                })
                ->check('#sepa-mandate-acceptance', true)
                ->radio('#proxy_is_default', true)
                ->click('#pay-now')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testPayWithSavedBankAccount()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->click('@pay-now-dropdown')
                ->clickLink('SEPA Direct Debit')
                ->click('.toggle-payment-with-token')
                ->click('#pay-now')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testRemoveBankAccount()
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
