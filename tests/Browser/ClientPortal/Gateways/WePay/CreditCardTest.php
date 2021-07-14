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

namespace Tests\Browser\ClientPortal\Gateways\WePay;

use App\Models\Client;
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

        CompanyGateway::where('gateway_key', '8fdeed552015b3c7b44ed6c8ebd9e992')->restore();

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });

        Client::first()->update(['postal_code' => 99501]);
    }

    public function testPayWithNewCard()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->type('card-number', '4003830171874018')
                ->type('card-holders-name', 'John Doe')
                ->type('.expiry', '12/28')
                ->type('cvc', '100')
                ->press('Pay Now')
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
                ->type('card-number', '4003830171874018')
                ->type('card-holders-name', 'John Doe')
                ->type('.expiry', '12/28')
                ->type('cvc', '100')
                ->radio('#proxy_is_default', true)
                ->press('Pay Now')
                ->waitForText('Details of the payment', 60)
                ->visitRoute('client.payment_methods.index')
                ->clickLink('View')
                ->assertSee('4018');
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
                ->waitForText('Credit Card')
                ->type('#cardholder_name', 'John Doe')
                ->type('card-number', '4003830171874018')
                ->type('card-holders-name', 'John Doe')
                ->type('.expiry', '12/28')
                ->type('cvc', '100')
                ->press('Add Payment Method')
                ->waitForText(4018, 60);
        });
    }
}
