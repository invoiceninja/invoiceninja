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

namespace Tests\Browser\ClientPortal\Gateways\Square;

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
                        ->type('#cardNumber', '4111 1111 1111 1111')
                        ->type('#expirationDate', '04/22')
                        ->type('#cvv', '1111')
                        ->type('#postalCode', '12345');
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
                        ->type('#cardNumber', '4111 1111 1111 1111')
                        ->type('#expirationDate', '04/22')
                        ->type('#cvv', '1111')
                        ->type('#postalCode', '12345');
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
                        ->type('#cardNumber', '4111 1111 1111 1111')
                        ->type('#expirationDate', '04/22')
                        ->type('#cvv', '1111')
                        ->type('#postalCode', '12345');
                })
                ->press('Add Payment Method')
                ->waitForText('**** 1111');
        });
    }
}
