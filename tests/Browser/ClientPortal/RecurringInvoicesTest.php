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

namespace Tests\Browser\ClientPortal;

use App\Models\RecurringInvoice;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class RecurringInvoicesTest extends DuskTestCase
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
                ->visitRoute('client.recurring_invoices.index')
                ->assertSeeIn('span[data-ref="meta-title"]', 'Recurring Invoices')
                ->visitRoute('client.logout');
        });
    }

    public function testRequestingCancellation()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.recurring_invoices.index')
                ->clickLink('View')
                ->assertSee('Cancellation')
                ->press('Request Cancellation')
                ->pause(1000)
                ->waitForText('Request cancellation')
                ->press('Confirm')
                ->pause(5000)
                ->assertPathIs(
                    route('client.recurring_invoices.request_cancellation', RecurringInvoice::first()->hashed_id, false)
                )
                ->visitRoute('client.logout');
        });
    }
}
