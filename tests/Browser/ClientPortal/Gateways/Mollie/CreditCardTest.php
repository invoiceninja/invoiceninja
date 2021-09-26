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

namespace Tests\Browser\ClientPortal\Gateways\Mollie;

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

        CompanyGateway::where('gateway_key', '1bd651fb213ca0c9d66ae3c336dc77e8')->restore();

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });
    }

    public function testPayWithNewCreditCard()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->pause(5000)
                ->withinFrame('iframe[name=cardNumber-input]', function (Browser $browser) {
                    $browser->type('#cardNumber', '4242424242424242');
                })
                ->withinFrame('iframe[name=cardHolder-input]', function (Browser $browser) {
                    $browser->type('#cardHolder', 'Invoice Ninja Test Suite');
                })
                ->withinFrame('iframe[name=expiryDate-input]', function (Browser $browser) {
                    $browser->type('#expiryDate', '12/29');
                })
                ->withinFrame('iframe[name=verificationCode-input]', function (Browser $browser) {
                    $browser->type('#verificationCode', '100');
                })
                ->press('Pay Now')
                ->waitForText('Details of the payment', 60);
        });
    }

    public function testPayWithNewCreditCardAndSaveForFutureUse()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->pause(5000)
                ->withinFrame('iframe[name=cardNumber-input]', function (Browser $browser) {
                    $browser->type('#cardNumber', '4242424242424242');
                })
                ->withinFrame('iframe[name=cardHolder-input]', function (Browser $browser) {
                    $browser->type('#cardHolder', 'Invoice Ninja Test Suite');
                })
                ->withinFrame('iframe[name=expiryDate-input]', function (Browser $browser) {
                    $browser->type('#expiryDate', '12/29');
                })
                ->withinFrame('iframe[name=verificationCode-input]', function (Browser $browser) {
                    $browser->type('#verificationCode', '100');
                })
                ->radio('#proxy_is_default', true)
                ->press('Pay Now')
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
                ->press('Pay Now')
                ->waitForText('Details of the payment', 60);
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
}
