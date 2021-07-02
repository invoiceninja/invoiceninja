<?php

namespace Tests\Browser\ClientPortal;

use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class PaymentMethodsTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (static::$browsers as $browser) {
            $browser->driver->manage()->deleteAllCookies();
        }
    }

    public function testPageLoads()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth()
                ->visitRoute('client.payment_methods.index')
                ->assertSee('Payment Methods');
        });
    }
}
