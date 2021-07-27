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

namespace Tests\Browser\ClientPortal\Gateways\PayTrace;

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

        CompanyGateway::where('gateway_key', 'bbd736b3254b0aabed6ad7fda1298c88')->restore();

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });
    }

    public function testPayingWithNewCreditCard()
    {
        $this->markTestSkipped('Credit card not supported.');

        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('@pay-now')
                ->press('Pay Now')
                ->clickLink('Credit Card')
                ->withinFrame('iframe', function (Browser $browser) {
                    $browser
                        ->type('CC', '4012000098765439')
                        ->select('EXP_MM', '12')
                        ->select('EXP_YY', '30')
                        ->type('SEC', '999');
                })
                ->press('Pay Now')
                ->waitForText('Details of the payment', 60);
        });
    }
}
