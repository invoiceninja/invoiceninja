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
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (static::$browsers as $browser) {
            $browser->driver->manage()->deleteAllCookies();
        }
    }

    public function testLoginPage()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit(route('client.login'))
                ->assertSee('Client Portal')
                ->type('email', 'user@example.com')
                ->type('password', 'password')
                ->press('Login');

            $browser->assertPathIs('/client/invoices');
        });
    }

    public function testLoginFormValidation()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit(route('client.login'))
                ->press('Login')
                ->assertSee('The email field is required.')
                ->assertSee('The password field is required.');
        });
    }

    public function testForgotPasswordLink()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit(route('client.login'))
                ->assertSeeLink('Forgot your password?')
                ->clickLink('Forgot your password?')
                ->assertPathIs('/client/password/reset');
        });
    }
}
