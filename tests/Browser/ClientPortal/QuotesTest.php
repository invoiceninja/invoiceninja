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

use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class QuotesTest extends DuskTestCase
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
                ->visitRoute('client.quotes.index')
                ->assertSeeIn('span[data-ref="meta-title"]', 'Quotes')
                ->visitRoute('client.logout');
        });
    }

    public function testClickingApproveWithoutQuotesDoesntWork()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.quotes.index')
                ->press('Approve')
                ->assertPathIs('/client/quotes');
        });
    }

    public function testApprovingQuotes()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.quotes.index')
                ->check('.form-check.form-check-child')
                ->press('Approve')
                ->assertPathIs('/client/quotes/approve')
                ->press('Approve')
                ->assertPathIs('/client/quotes')
                ->assertSee('Quote(s) approved successfully.')
                ->visitRoute('client.logout');
        });
    }

    public function testQuotesWithSentStatusCanOnlyBeApproved()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.quotes.index')
                ->clickLink('View')
                ->assertSee('Only quotes with "Sent" status can be approved.')
                ->visitRoute('client.logout');
        });
    }

    public function testMessageForNonApprovableQuotesIsVisible()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.quotes.index')
                ->check('.form-check.form-check-child')
                ->press('Approve')
                ->assertPathIs('/client/quotes')
                ->assertDontSee('Quote(s) approved successfully.')
                ->assertSee('Only quotes with "Sent" status can be approved.')
                ->visitRoute('client.logout');
        });
    }

    public function testNoQuotesAvailableForDownloadMessage()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.quotes.index')
                ->press('Download')
                ->assertSee('No quotes available for download.');
        });
    }
}
