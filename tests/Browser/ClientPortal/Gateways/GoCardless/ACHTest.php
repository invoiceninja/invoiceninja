<?php

namespace Tests\Browser\ClientPortal\Gateways\GoCardless;

use App\Models\CompanyGateway;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class ACHTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (static::$browsers as $browser) {
            $browser->driver->manage()->deleteAllCookies();
        }

        $this->disableCompanyGateways();

        CompanyGateway::where('gateway_key', 'b9886f9257f0c6ee7c302f1c74475f6c')->restore();

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });
    }

    public function testPayingWithNoPreauthorizedIsntPossible()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Bank Transfer')
                ->assertSee('To pay with a bank account, first you have to add it as payment method.');
        });
    }
}
