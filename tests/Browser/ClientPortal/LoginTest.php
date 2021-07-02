<?php

namespace Tests\Browser\ClientPortal;

use Illuminate\Foundation\Testing\DatabaseMigrations;
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
