<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{

    use DatabaseTransactions;

    public function setUp()
    {
        parent::setUp();
        Session::start();

    }

    public function testLoginFormDisplayed()
    {

        $response = $this->get('/login', [
            '_token' => csrf_token()
        ]);

        $response->assertStatus(200);
    }
    /**
     * A valid user can be logged in.
     *
     * @return void
     */
    public function testLoginAValidUser()
    {
        $account = factory(Account::class)->create();
        $user = factory(User::class)->create([
            'account_id' => $account->id,
        ]);
        $company = factory(\App\Models\Company::class)->make([
            'account_id' => $account->id,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
        ]);

        $response = $this->post('/login', [
            'email' => config('ninja.testvars.username'),
            'password' => config('ninja.testvars.password'),
            '_token' => csrf_token()

        ]);

        //$response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * An invalid user cannot be logged in.
     *
     * @return void
     */
    public function testDoesNotLoginAnInvalidUser()
    {
        $account = factory(Account::class)->create();
        $user = factory(User::class)->create([
            'account_id' => $account->id,
        ]);
        $company = factory(\App\Models\Company::class)->make([
            'account_id' => $account->id,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
        ]);

        $response = $this->post('/login', [
            'email' => config('ninja.testvars.username'),
            'password' => 'invaliddfd',
            '_token' => csrf_token()
        ]);

        //$response->assertSessionHasErrors();
        $this->assertGuest();
    }
    /**
     * A logged in user can be logged out.
     *
     * @return void
     */
    public function testLogoutAnAuthenticatedUser()
    {
        $account = factory(Account::class)->create();
        $user = factory(User::class)->create([
            'account_id' => $account->id,
        ]);
        $company = factory(\App\Models\Company::class)->make([
            'account_id' => $account->id,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
        ]);

        $response = $this->actingAs($user)->post('/logout',[
            '_token' => csrf_token()
        ]);
        $response->assertStatus(302);
        $this->assertGuest();
    }
}
