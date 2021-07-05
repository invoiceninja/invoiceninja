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

class InvoicesTest extends DuskTestCase
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
                ->assertSee('Invoices')
                ->visitRoute('client.logout');
        });
    }

    public function testClickingPayNowWithoutInvoices()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->press('Pay Now')
                ->assertSee('No payable invoices selected. Make sure you are not trying to pay draft invoice or invoice with zero balance due.')
                ->visitRoute('client.logout');
        });
    }

    public function testClickingDownloadWithoutInvoices()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->press('Download')
                ->assertSee('No items selected.')
                ->visitRoute('client.logout');
        });
    }

    public function testCheckingInvoiceAndClickingPayNow()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->check('.form-check.form-check-child')
                ->press('Pay Now')
                ->assertPathIs('/client/invoices/payment')
                ->visitRoute('client.logout');
        });
    }
}
