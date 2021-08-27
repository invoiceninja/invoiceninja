<?php

namespace Tests\Browser\ClientPortal\Gateways\Braintree;

use App\DataMapper\FeesAndLimits;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });

        $this->disableCompanyGateways();

        CompanyGateway::where('gateway_key', 'f7ec488676d310683fb51802d076d713')->restore();

        $cg = CompanyGateway::where('gateway_key', 'f7ec488676d310683fb51802d076d713')->firstOrFail();
        $fees_and_limits = $cg->fees_and_limits;
        $fees_and_limits->{GatewayType::BANK_TRANSFER} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        $company = Company::first();
        $settings = $company->settings;

        $settings->client_portal_allow_under_payment = true;
        $settings->client_portal_allow_over_payment = true;

        $company->settings = $settings;
        $company->save();
    }
}
