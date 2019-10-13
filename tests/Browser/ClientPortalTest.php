<?php

namespace Tests\Browser;

use App\DataMapper\DefaultSettings;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ClientPortalTest extends DuskTestCase
{

    use WithFaker;
    use MakesHash;

    public function testLoginPageDisplayed()
    {

        $this->browse(function ($browser){
            $browser->visit('/client/login')
                    ->assertPathIs('/client/login');
        });
    
    }

    /**
     * A valid user can be logged in.
     *
     * @return void
     */
    public function testLoginAValidUser()
    {
        \Eloquent::unguard();

        $faker = \Faker\Factory::create();

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
            'domain' => 'ninja.test',
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = factory(\App\Models\User::class)->create([
            'email'             => $faker->email,
           // 'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default'))
        ]);

        $company_token = \App\Models\CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => \Illuminate\Support\Str::random(64),
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => json_encode([]),
            'settings' => json_encode(DefaultSettings::userSettings()),
        ]);

        $client = factory(\App\Models\Client::class)->create([
            'user_id' => $user->id,
            'company_id' => $company->id
        ]);


        $contact = new ClientContact;
        $contact->first_name = $faker->firstName;
        $contact->last_name = $faker->lastName;
        $contact->email = $faker->email;
        $contact->company_id = $company->id;
        $contact->password = Hash::make(config('ninja.testvars.password'));
        $contact->email_verified_at =  now();
        $contact->client_id = $client->id;
        $contact->save();


        $this->browse(function ($browser) use ($contact) {
            $browser->visit('/client/login')
                    ->type('email', $contact->email)
                    ->type('password', config('ninja.testvars.password'))
                    ->press('Login')
                    ->assertPathIs('/client/dashboard');

            $browser->visit('client/invoices')
                    ->assertSee('Invoice Number');

            $browser->with('.table', function ($table) {
                $table->assertSee('Invoice Date');
            });

            $browser->visit('client/payments')
                ->assertSee('Payment Date');

            $browser->visit('client/recurring_invoices')
                ->assertSee('Frequency');

            $browser->visit('client/logout')
                    ->assertPathIs('/client/login');


        });


    }

    /**
     * Testing sidebar pages availability.
     * 
     * @return void 
     */
    public function testDashboardElements(): void
    {
        $this->browse(function ($browser) {

            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $browser->visit('/client/dashboard')
                ->assertSee(strtoupper(ctrans('texts.total_invoiced')))
                ->assertSee(strtoupper(ctrans('texts.paid_to_date')))
                ->assertSee(strtoupper(ctrans('texts.open_balance')))
                ->assertSee(ctrans('texts.client_information'))
                ->assertSee(\App\Models\Client::first()->name)
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

            $browser->visit('/client/invoices')
                ->assertSee(ctrans('texts.pay_now'))
                ->waitFor('.dataTable')
                ->assertVisible('.page-link')
                ->assertVisible('tr.odd')
                ->assertVisible('#datatable_info')
                ->assertMissing('.dataTables_empty')
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    /**
     * Testing recurring invoices list.
     *
     * @return void
     */
    public function testRecurringInvoicesElements(): void
    {
        $this->browse(function ($browser) {

            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $browser->visit('/client/recurring_invoices')
                ->waitFor('.dataTable')
                ->assertVisible('.page-link')
                ->assertVisible('#datatable_info')
                ->visit('client/logout')
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

            $browser->visit('/client/payments')
                ->waitFor('.dataTable')
                ->assertVisible('#datatable_info')
                ->visit('client/logout')
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
                ->waitFor('.dataTable')
                ->assertVisible('#datatable_info')
                ->assertVisible('.dataTables_empty')
                ->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    public function testProfilePageContactUpdate(): void
    {
        $faker = \Faker\Factory::create();

        $this->browse(function ($browser)  use ($faker) {

            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $client_contact = ClientContact::where('email', 'user@example.com')->first();

            $browser->maximize();

            $browser->visit(sprintf('/client/profile/%s/edit', $client_contact->client->user->hashed_id))
                ->assertSee(ctrans('texts.details'));

            $first_name = $browser->value('#first_name');
            
            $browser->value('#first_name', $faker->firstName);

            $browser->assertSee(ctrans('texts.save'))
                ->press(ctrans('texts.save'));

            $this->assertNotEquals($first_name, $browser->value('#first_name'));

            $browser->visit('client/logout')
                ->assertPathIs('/client/login');
        });
    }

    /**
     * Test 'profile page' updating functions.
     *
     * @return void
     */
    public function testProfilePageClientUpdate(): void
    {
        $faker = \Faker\Factory::create();

        $this->browse(function ($browser) use ($faker) {

            $browser->visit('/client/login')
                ->type('email', 'user@example.com')
                ->type('password', config('ninja.testvars.password'))
                ->press('Login')
                ->assertPathIs('/client/dashboard');

            $client_contact = ClientContact::where('email', 'user@example.com')->first();

            $browser->visit(sprintf('/client/profile/%s/edit', $client_contact->client->user->hashed_id))
                ->assertSee(ctrans('texts.client_information'));

            $browser->driver->executeScript('window.scrollTo(0, document.body.scrollHeight)');

            $browser->value('#name', '')
                ->assertVisible('#update_settings > .card > .card-body > button')
                ->click('#update_settings > .card > .card-body > button')
                ->assertVisible('.invalid-feedback');


            $name = $browser->value('#name');

            $browser->maximize();
            $browser->driver->executeScript('window.scrollTo(0, document.body.scrollHeight)');

            $browser->value('#name', $faker->name)
                ->assertVisible('#update_settings > .card > .card-body > button')
                ->click('#update_settings > .card > .card-body > button');

            $this->assertNotEquals($name, $browser->value('#name'));

            $browser->driver->executeScript('window.scrollTo(0, document.body.scrollHeight)');
        });
    }

}