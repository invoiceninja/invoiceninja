<?php

namespace Tests\Browser\Pages\ClientPortal;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class Login extends Page
{
    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url(): string
    {
        return '/client/login';
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param  Browser  $browser
     * @return void
     */
    public function assert(Browser $browser)
    {
        $browser->assertPathIs($this->url());
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@element' => '#selector',
        ];
    }

    public function auth(Browser $browser)
    {
        $browser
            ->visitRoute('client.login')
            ->type('email', 'user@example.com')
            ->type('password', 'password')
            ->press('Login');
    }
}
