<?php

namespace Tests\Browser\ClientPortal;

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
    }

    public function testPageLoads()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth()
                ->visitRoute('client.payments.index')
                ->assertSee('Recurring Invoices');
        });
    }
}
