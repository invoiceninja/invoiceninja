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

class KBCTest extends DuskTestCase
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

    public function testSuccessfulPayment(): void
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Undefined.')
                ->waitForText('Test profile')
                ->press('CBC')
                ->radio('final_state', 'paid')
                ->press('Continue')
                ->waitForText('Details of the payment')
                ->assertSee('Completed');
        });
    }

    public function testFailedPayment(): void
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Undefined.')
                ->waitForText('Test profile')
                ->press('CBC')
                ->radio('final_state', 'failed')
                ->press('Continue')
                ->waitForText('Failed.');
        });
    }

    public function testCancelledTest(): void
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Undefined.')
                ->waitForText('Test profile')
                ->press('CBC')
                ->radio('final_state', 'canceled')
                ->press('Continue')
                ->waitForText('Cancelled.');
        });
    }
}
