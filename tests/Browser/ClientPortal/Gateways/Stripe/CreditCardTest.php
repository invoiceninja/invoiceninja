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

        // Enable Stripe.
        CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->restore();

        $cg = CompanyGateway::where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')->firstOrFail();
        $fees_and_limits = $cg->fees_and_limits;
        $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();
    }

    public function testPaymentWithNewCard()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->click('@pay-now-dropdown')
                ->clickLink('Credit Card')
                ->type('#cardholder-name', 'John Doe')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('cardnumber', '4242 4242 4242 4242')
                        ->type('exp-date', '04/22')
                        ->type('cvc', '242');
                })
                ->click('#pay-now')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testPayWithNewCardAndSaveForFutureUse()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->click('@pay-now-dropdown')
                ->clickLink('Credit Card')
                ->type('#cardholder-name', 'John Doe')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('cardnumber', '4242 4242 4242 4242')
                        ->type('exp-date', '04/22')
                        ->type('cvc', '242');
                })
                ->radio('#proxy_is_default', true)
                ->click('#pay-now')
                ->waitForText('Details of the payment', 60)
                ->visitRoute('client.payment_methods.index')
                ->clickLink('View')
                ->assertSee('4242');
        });
    }

    public function testPayWithSavedCreditCard()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->click('@pay-now-dropdown')
                ->clickLink('Credit Card')
                ->click('.toggle-payment-with-token')
                ->click('#pay-now')
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

    public function testAddingCreditCardStandalone()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->press('Add Payment Method')
                ->clickLink('Credit Card')
                ->type('#cardholder-name', 'John Doe')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('cardnumber', '4242 4242 4242 4242')
                        ->type('exp-date', '04/22')
                        ->type('cvc', '242');
                })
                ->press('Add Payment Method')
                ->waitForText('**** 4242');
        });
    }
}
