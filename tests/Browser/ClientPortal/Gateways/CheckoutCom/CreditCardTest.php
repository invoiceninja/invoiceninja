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

namespace Tests\Browser\ClientPortal\Gateways\CheckoutCom;

use App\Models\CompanyGateway;
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

        $this->disableCompanyGateways();

        CompanyGateway::where('gateway_key', '3758e7f7c6f4cecf0f4f348b9a00f456')->restore();

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });
    }

    public function testPayWithNewCard()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('cardnumber', '4242424242424242')
                        ->type('exp-date', '04/22')
                        ->type('cvc', '100');
                })
                ->press('#pay-button')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testPayWithNewCardAndSaveForFutureUse()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('cardnumber', '4242424242424242')
                        ->type('exp-date', '04/22')
                        ->type('cvc', '100');
                })
                ->radio('#proxy_is_default', true)
                ->press('#pay-button')
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

    public function testAddingCreditCardStandalone()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->press('Add Payment Method')
                ->clickLink('Credit Card')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('cardnumber', '4242424242424242')
                        ->type('exp-date', '04/22')
                        ->type('cvc', '100');
                })
                ->press('#pay-button')
                ->waitForText('Details of payment method', 60);
        });
    }
}
