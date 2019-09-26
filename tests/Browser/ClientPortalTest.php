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

}
