<?php

namespace Tests\Browser;

use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\DuskTestCase;

class ClientPortalTest extends DuskTestCase
{
    use WithFaker;
    use MakesHash;

    public $contact;

    public function tearDown(): void
    {
        parent::tearDown();

        $this->browse(function ($browser) {
            $browser->driver->manage()->deleteAllCookies();
        });
    }

    public function testLoginPageDisplayed()
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->assertPathIs('/client/login');
        });
    }

    public function testLoginAValidUser()
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard')
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    public function testDashboardElements(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $browser->visit('/client/dashboard')
                ->assertSee(ctrans('texts.quick_overview_statistics'))
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    /**
     * Test list of invoices.
     *
     * @return void
     */
    public function testInvoicesElements(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $invoice = Invoice::first();

            $browser->visit('/client/invoices')
                ->assertSee(ctrans('texts.pay_now'))
                ->assertSee($invoice->number)
                ->clickLink(ctrans('texts.view'))
                ->assertPathIs(route('client.invoice.show', $invoice->hashed_id, false))
                ->assertSee(ctrans('texts.pay_now'));

            $browser->visit('/client/invoices')
                ->check('#paid')
                ->assertSee(ctrans('texts.paid'))
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    public function testRecurringInvoicesElements(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $invoice = RecurringInvoice::first();

            $browser->visit('/client/recurring_invoices')
                ->assertSee(ctrans('texts.recurring_invoices'))
                ->clickLink(ctrans('texts.view'))
                ->assertPathIs(route('client.recurring_invoices.show', $invoice->hashed_id, false))
                ->visit('/client/logout')
                ->assertPathIs('/client/login');
        });
    }

    /**
     * List of payments.
     *
     * @return void
     */
    public function testPaymentsElements(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $payment = Payment::first();

            $browser->visit('/client/payments')
                ->clickLink(ctrans('texts.view'))
                ->assertPathIs(route('client.payments.show', $payment->hashed_id, false))
                ->visit('/client/logout')
                ->assertPathIs('/client/login');
        });
    }

    /**
     * List of payment methods.
     *
     * @return void
     */
    public function testPaymentMethodsElements(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $browser->visit('/client/payment_methods')
                ->assertSee('No results found.')
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    public function testQuotesElements(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $credit = Credit::first();

            $browser->visit('/client/quotes')
                ->clickLink(ctrans('texts.view'))
                ->assertPathIs(route('client.credits.show', $credit->hashed_id, false))
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    public function testCreditsElements(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $browser->visit('/client/credits')
                ->assertSee('No results found.')
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    public function testProfilePageContactUpdate(): void
    {
        $faker = \Faker\Factory::create();

        $this->browse(function ($browser) use ($faker) {
            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $client_contact = ClientContact::where('email', 'user@example.com')->first();

            $browser->maximize();

            $browser->visit(sprintf('/client/profile/%s/edit', $client_contact->client->user->hashed_id))
                ->assertSee(ctrans('texts.profile'));

            $first_name = $browser->value('#first_name');

            $browser->value('#first_name', $faker->firstName);

            $browser->assertSee(ctrans('texts.save'))
                ->press(ctrans('texts.save'));

            $this->assertNotEquals($first_name, $browser->value('#first_name'));

            $browser->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }
}
