<?php

namespace Tests\Browser\ClientPortal;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class ProfileSettingsTest extends DuskTestCase
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

    public function testPageLoads()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('button[data-ref="client-profile-dropdown"]')
                ->click('a[data-ref="client-profile-dropdown-settings"]')
                ->waitForText('Client Information')
                ->assertSeeIn('span[data-ref="meta-title"]', 'Client Information')
                ->visitRoute('client.logout');
        });
    }
}
