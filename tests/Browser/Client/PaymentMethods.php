<?php

namespace Tests\Browser\Client;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PaymentMethods extends DuskTestCase
{

    public function testAddPaymentMethodPage(): void
    {
        $this->browse(function (Browser $browser) {

            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $browser->visit(route('client.payment_methods.index'))
                ->waitFor('.dataTable')
                ->waitFor('.dataTables_empty')
                ->assertSee('No records found');

            // TODO: Testing Stripe <iframe>

            $browser->visit(route('client.payment_methods.create'))
                ->assertSee('Add Payment Method')
                ->assertSee('Save');
        });
    }
}
