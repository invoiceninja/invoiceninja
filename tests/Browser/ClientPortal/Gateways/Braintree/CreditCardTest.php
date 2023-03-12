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

namespace Tests\Browser\ClientPortal\Gateways\Braintree;

use App\DataMapper\FeesAndLimits;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class CreditCardTest extends DuskTestCase
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
        $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        $company = Company::first();
        $settings = $company->settings;

        $settings->client_portal_allow_under_payment = true;
        $settings->client_portal_allow_over_payment = true;

        $company->settings = $settings;
        $company->save();
    }

    public function testPayWithNewCard()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->type('@underpayment-input', '100')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->waitFor('#braintree-hosted-field-number', 60)
                ->withinFrame('#braintree-hosted-field-number', function (Browser $browser) {
                    $browser->type('credit-card-number', '4111111111111111');
                })
                ->withinFrame('#braintree-hosted-field-expirationDate', function (Browser $browser) {
                    $browser->type('expiration', '04/25');
                })
                ->press('Pay Now')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testPayWithNewCardAndSaveForFuture()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->type('@underpayment-input', '100')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->waitFor('#braintree-hosted-field-number', 60)
                ->withinFrame('#braintree-hosted-field-number', function (Browser $browser) {
                    $browser->typeSlowly('credit-card-number', '4005519200000004');
                })
                ->withinFrame('#braintree-hosted-field-expirationDate', function (Browser $browser) {
                    $browser->typeSlowly('expiration', '04/25');
                })
                ->radio('#proxy_is_default', true)
                ->press('Pay Now')
                ->waitForText('Details of the payment', 60)
                ->visitRoute('client.payment_methods.index')
                ->clickLink('View')
                ->assertSee('0004');
        });
    }

    public function testPayWithSavedCard()
    {
        $this->markTestSkipped('Works in "real" browser, otherwise giving error code 0.');

        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->type('@underpayment-input', '100')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->click('.toggle-payment-with-token')
                ->click('#pay-now-with-token')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testRemoveCreditCard()
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

    public function testAddingPaymentMethodShouldntBePossible()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->press('Add Payment Method')
                ->clickLink('Credit Card')
                ->assertSee('This payment method can be can saved for future use, once you complete your first transaction. Don\'t forget to check "Store credit card details" during payment process.');
        });
    }
}
