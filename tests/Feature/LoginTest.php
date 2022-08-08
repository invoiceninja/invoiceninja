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
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\Auth\LoginController
 */
class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();
        Session::start();
    }

    public function testLoginFormDisplayed()
    {
        $response = $this->get('/login', [
            '_token' => csrf_token(),
        ]);

        $response->assertStatus(404);
    }

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
            'email' => 'test@example.com',
            'password' => \Hash::make('123456'),
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $company_token = new CompanyToken;
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

        $this->assertEquals($user->email, 'test@example.com');
        $this->assertTrue(\Hash::check('123456', $user->password));

        $data = [
            'email' => 'test@example.com',
            'password' => '123456',
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/login', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog(print_r($message, 1));
        }

        $arr = $response->json();

        // nlog(print_r($arr, 1));

        $response->assertStatus(200);
    }
}
