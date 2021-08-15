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

namespace Tests\Browser\ClientPortal\Gateways\Eway;

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

    public function testAddingCreditCardStandalone()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.payment_methods.index')
                ->press('Add Payment Method')
                ->clickLink('Credit Card')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('EWAY_CARDNAME', 'Invoice Ninja')
                        ->type('EWAY_CARDNUMBER', '4111 1111 1111 1111')
                        ->type('EWAY_CARDEXPIRY', '04/22')
                        ->type('EWAY_CARDCVN', '100');
                })
                ->press('Add Payment Method')
                ->waitForText('**** 1111');
        });
    }
}
