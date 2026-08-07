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

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\PasskeyCredential;
use App\Models\User;
use App\Services\Auth\Passkeys\PasskeyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\Auth\LoginController
 */
class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();
    }

    // public function testLoginFormDisplayed()
    // {
    //     $response = $this->get('/login', [
    //         '_token' => csrf_token(),
    //     ]);

    //     $response->assertStatus(404);
    // }

    /**
     * A valid user can be logged in.
     *
     * @return void
     */
    // public function testLoginAValidUser()
    // {
    //     $account = factory(Account::class)->create();
    //     $user = factory(User::class)->create([
    //       //  'account_id' => $account->id,
    //     ]);
    //     $company = Company::factory()->make([
    //         'account_id' => $account->id,
    //     ]);

    //     $user->companies()->attach($company->id, [
    //         'account_id' => $account->id,
    //         'is_owner' => 1,
    //         'is_admin' => 1,
    //     ]);

    //     $response = $this->post('/login', [
    //         'email' => config('ninja.testvars.username'),
    //         'password' => config('ninja.testvars.password'),
    //         '_token' => csrf_token()

    //     ]);

    //     //$response->assertStatus(302);
    //     $this->assertAuthenticatedAs($user);
    // }

    /**
     * An invalid user cannot be logged in.
     *
     * @return void
     */
    // public function testDoesNotLoginAnInvalidUser()
    // {
    //     $account = factory(Account::class)->create();
    //     $user = factory(User::class)->create([
    //     //    'account_id' => $account->id,
    //     ]);
    //     $company = Company::factory()->make([
    //         'account_id' => $account->id,
    //     ]);

    //     $user->companies()->attach($company->id, [
    //         'account_id' => $account->id,
    //         'is_owner' => 1,
    //         'is_admin' => 1,
    //     ]);

    //     $response = $this->post('/login', [
    //         'email' => config('ninja.testvars.username'),
    //         'password' => 'invaliddfd',
    //         '_token' => csrf_token()
    //     ]);

    //     //$response->assertSessionHasErrors();
    //     $this->assertGuest();
    // }
    // /**
    //  * A logged in user can be logged out.
    //  *
    //  * @return void
    //  */
    // public function testLogoutAnAuthenticatedUser()
    // {
    //     $account = factory(Account::class)->create();
    //     $user = factory(User::class)->create([
    //     //    'account_id' => $account->id,
    //     ]);
    //     $company = Company::factory()->make([
    //         'account_id' => $account->id,
    //     ]);

    //     $user->companies()->attach($company->id, [
    //         'account_id' => $account->id,
    //         'is_owner' => 1,
    //         'is_admin' => 1,
    //     ]);

    //     $response = $this->actingAs($user)->post('/logout',[
    //         '_token' => csrf_token()
    //     ]);
    //     $response->assertStatus(302);

    //    // $this->assertGuest();
    // }

    public function testApiLogin()
    {
        Account::all()->each(function ($account) {
            $account->delete();
        });

        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'test@gmail.com',
            'password' => \Hash::make('123456'),
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = $user->first_name.' '.$user->last_name;
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'is_admin' => 1,
        ]);

        $user->fresh();

        $this->assertTrue($user->companies !== null);
        $this->assertTrue($user->company_users !== null);
        $this->assertTrue($user->company_users->first() !== null);
        $this->assertTrue($user->account !== null);

        $this->assertEquals($user->email, 'test@gmail.com');
        $this->assertTrue(\Hash::check('123456', $user->password));

        $data = [
            'email' => 'test@gmail.com',
            'password' => '123456',
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
        ])->postJson('/api/v1/login', $data);


        $arr = $response->json();

        // nlog(print_r($arr, 1));

        $response->assertStatus(200);
    }

    public function testPrecheckReturnsPasswordOnlyForUserWithoutTwoFactor()
    {
        Account::all()->each(function ($account) {
            $account->delete();
        });

        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'precheck-nottfa@gmail.com',
            'password' => \Hash::make('123456'),
            'google_2fa_secret' => null,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
        ])->postJson('/api/v1/login/precheck', [
            'email' => 'precheck-nottfa@gmail.com',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(['password'], $response->json('methods'));
    }

    public function testPrecheckReturnsTotpForUserWithTwoFactor()
    {
        Account::all()->each(function ($account) {
            $account->delete();
        });

        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'precheck-tfa@gmail.com',
            'password' => \Hash::make('123456'),
            'google_2fa_secret' => encrypt('PADTOTPDUMMYSECRET'),
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
        ])->postJson('/api/v1/login/precheck', [
            'email' => 'precheck-tfa@gmail.com',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(['password', 'totp'], $response->json('methods'));
    }

    public function testPrecheckIsEnumerationResistantForUnknownEmail()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
        ])->postJson('/api/v1/login/precheck', [
            'email' => 'this-account-does-not-exist@gmail.com',
        ]);

        $response->assertStatus(200);

        // An unknown email must return the identical payload to an existing
        // account that has no 2FA, so account existence cannot be inferred.
        $this->assertEquals(['password'], $response->json('methods'));
    }

    public function testApiLoginSucceedsWithPasswordWhenPasskeyExists()
    {
        Account::all()->each(function ($account) {
            $account->delete();
        });

        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'passkey@gmail.com',
            'password' => \Hash::make('123456'),
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = $user->first_name.' '.$user->last_name;
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'is_admin' => 1,
        ]);

        $passkey = new PasskeyCredential();
        $passkey->account_id = $account->id;
        $passkey->user_id = $user->id;
        $passkey->name = 'MacBook';
        $passkey->credential_id = base64_encode('test-credential');
        $passkey->credential_public_key = base64_encode('test-public-key');
        $passkey->signature_counter = 0;
        $passkey->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
        ])->postJson('/api/v1/login', [
            'email' => 'passkey@gmail.com',
            'password' => '123456',
        ]);

        $response->assertStatus(200);
    }

    public function testPasskeyLoginOptionsReturns404WhenUserHasNoPasskeys()
    {
        Account::all()->each(function ($account) {
            $account->delete();
        });

        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'nopasskey@gmail.com',
            'password' => \Hash::make('123456'),
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'is_admin' => 1,
        ]);

        $response = $this->postJson('/api/v1/passkeys/login/options', [
            'email' => 'nopasskey@gmail.com',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'These credentials do not match our records');
    }
}
