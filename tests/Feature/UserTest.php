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

use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Libraries\MultiDB;
use App\Utils\TruthSource;
use Tests\MockAccountData;
use App\Models\CompanyUser;
use App\Models\CompanyToken;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Http\Middleware\PasswordProtection;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 *
 *  App\Http\Controllers\UserController
 */
class UserTest extends TestCase
{
    use MockAccountData;

    private $default_email = 'attach@gmail.com';
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(
            ThrottleRequests::class,
            PasswordProtection::class
        );

        // $this->makeTestData();

        // $this->withoutExceptionHandling();

    }

    private function mockAccount()
    {

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
        ]);

        $user->password = \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword');
        $user->email_verified_at = now();
        $user->save();

        auth()->login($user, false);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        // $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
        // $cu->is_owner = true;
        // $cu->is_admin = true;
        // $cu->is_locked = false;
        // $cu->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => \App\DataMapper\CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        $token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $token;
        $company_token->is_system = true;
        $company_token->save();

        // auth()->user()->setContext($company, $company_token);

        $truth = app()->make(TruthSource::class);

        $truth->setCompanyUser($company_token->cu);
        $truth->setUser($company_token->user);
        $truth->setCompany($company_token->company);
        $truth->setCompanyToken($company_token);

        return $company_token;

    }


    public function testValidEmailUpdate()
    {
        $company_token = $this->mockAccount();
        $user = auth()->user();

        // $user = $company_token->user;
        // $user->load('company_user');
        // nlog($company_token->toArray());

        // $user = User::with('company_user')->find($company_token->user_id);
        // nlog($user->toArray());

        $data = $user->toArray();

        nlog($data);
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(200);

        $data['email'] = 'newemail@gmail.com';

    }


    public function testNullEmail()
    {

        $company_token = $this->mockAccount();
        // $user = $company_token->user;
        // $user->load('company_user');

        $user = auth()->user();

        $data = $user->toArray();
        $data['email'] = '';
        unset($data['password']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(200);

        $data = $user->toArray();
        unset($data['password']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(200);

        $data = $user->toArray();

        $data['email'] = \Illuminate\Support\Str::random(32)."@example.com";
        unset($data['password']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals($arr['data']['email'], $data['email']);
    }


    public function testUserLocale()
    {

        $company_token = $this->mockAccount();

        $user = auth()->user();

        $user->language_id = "13";
        $user->save();

        $this->assertEquals("fr_CA", $user->getLocale());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
        ])->get('/api/v1/statics');

        $response->assertStatus(200);

    }



    public function testUserResponse()
    {
        $company_token = $this->mockAccount();

        $_user = MultiDB::hasUser(['email' => 'normal_user@gmail.com']);

        if ($_user) {
            $_user->account->delete();
        }

        $data = [
                'first_name' => 'hey',
                'last_name' => 'you',
                'email' => 'normal_user@gmail.com',
                'company_user' => [
                    'is_admin' => true,
                    'is_owner' => false,
                    'permissions' => 'create_client,create_invoice',
                ],
                'phone' => null,
            ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->post('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $user = $response->json();
        $user_id = $user['data']['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/users', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertCount(2, $arr['data']);

        //archive the user we just created:

        $data = [
            'action' => 'archive',
            'ids' => [$user_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users/bulk', $data);

        $response->assertStatus(200);

        $this->assertCount(1, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?status=active&without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?status=archived&without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?status=deleted&without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json()['data']);


    }

    public function testUserAttemptingtToDeleteThemselves()
    {

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
            'password' => \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword'),
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => \App\DataMapper\CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        $token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $token;
        $company_token->is_system = true;
        $company_token->save();

        $data = [
            'ids' => [$user->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users/bulk?action=delete', $data);


        $response->assertStatus(401);

    }

    // public function testDisconnectUserOauthMailer()
    // {
    //     $account = Account::factory()->create([
    //         'hosted_client_count' => 1000,
    //         'hosted_company_count' => 1000,
    //     ]);

    //     $user =
    //     User::factory()->create([
    //         'account_id' => $account->id,
    //         'email' => $this->faker->safeEmail(),
    //         'oauth_user_id' => '123456789',
    //         'oauth_provider_id' => '123456789',
    //     ]);

    //     $response = $this->withHeaders([
    //         'X-API-TOKEN' => $this->token,
    //     ])->post("/api/v1/users/{$user->hashed_id}/disconnect_mailer");

    //     $response->assertStatus(200);

    //     $user->fresh();

    //     $this->assertNull($user->oauth_user_token);
    //     $this->assertNull($user->oauth_user_refresh_token);

    // }

    public function testUserFiltersWith()
    {
        $company_token = $this->mockAccount();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/users?with='.$company_token->user->hashed_id);

        $response->assertStatus(200);
    }

    public function testUserList()
    {


        $company_token = $this->mockAccount();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/users');

        $response->assertStatus(200);
    }

    public function testValidationRulesPhoneIsNull()
    {
        $this->withoutMiddleware(PasswordProtection::class);
        $company_token = $this->mockAccount();

        $_user = MultiDB::hasUser(['email' => 'bob1@good.ole.boys.com']);

        if ($_user) {
            $_user->account->delete();
        }

        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
            ],
            'phone' => null,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);
    }

    public function testValidationRulesPhoneIsBlankString()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $_user = MultiDB::hasUser(['email' => 'bob1@good.ole.boys.com']);

        if ($_user) {
            $_user->account->delete();
        }

        $company_token = $this->mockAccount();
        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
            ],
            'phone' => "",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $user_id = $this->decodePrimaryKey($arr['data']['id']);
        $user = User::find($user_id);


        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
                'notifications' => '',
            ],
            'phone' => "",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);
    }

    public function testUserStore()
    {
        $this->withoutMiddleware(PasswordProtection::class);


        $_user = MultiDB::hasUser(['email' => 'bob1@good.ole.boys.com']);

        if ($_user) {
            $_user->account->delete();
        }

        $company_token = $this->mockAccount();
        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
                'notifications' => '',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
    }

    public function testUserAttachAndDetach()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $_user = MultiDB::hasUser(['email' => $this->default_email]);

        if ($_user) {
            $_user->account->delete();
        }

        $company_token = $this->mockAccount();
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Palloni',
            'email' => $this->default_email,
        ];

        $response = false;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $company_token->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->delete('/api/v1/users/'.$arr['data']['id'].'/detach_from_company?include=company_user');

        $response->assertStatus(200);

        $user_id = $this->decodePrimaryKey($arr['data']['id']);

        $cu = CompanyUser::whereUserId($user_id)->whereCompanyId($company_token->company->id)->first();
        $ct = CompanyToken::whereUserId($user_id)->whereCompanyId($company_token->company->id)->first();
        $user = User::find($user_id);

        $this->assertNull($cu);
        $this->assertNull($ct);
        $this->assertNotNull($user);
    }

    public function testAttachUserToMultipleCompanies()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = $this->mockAccount();

        $_user = MultiDB::hasUser(['email' => $this->default_email]);

        if ($_user) {
            $_user->account->delete();
        }


        $_user = MultiDB::hasUser(['email' => 'bob@good.ole.boys.co2.com']);

        if ($_user) {
            $_user->account->delete();
        }


        /* Create New Company */
        $company2 = Company::factory()->create([
            'account_id' => $company_token->account_id,
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = auth()->user()->id;
        $company_token->company_id = $company2->id;
        $company_token->account_id = auth()->user()->account_id;
        $company_token->name = 'test token';
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        /*Manually link this user to the company*/
        auth()->user()->companies()->attach($company2->id, [
             'account_id' => $company_token->account_id,
             'is_owner' => 1,
             'is_admin' => 1,
             'is_locked' => 0,
             'permissions' => '',
             'notifications' => \App\DataMapper\CompanySettings::notificationAdminDefaults(),
             'settings' => null,
         ]);

        /*Create New Blank User and Attach to Company 2*/
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Palloni',
            'email' => $this->default_email,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        // $this->assertNotNull($new_user->company_user);
        // $this->assertEquals($new_user->company_user->company_id, $company2->id);

        /*Create brand new user manually with company_user object and attach to a different company*/
        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob@good.ole.boys.co2.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
        $this->assertFalse($arr['data']['company_user']['is_admin']);
        $this->assertFalse($arr['data']['company_user']['is_owner']);
        $this->assertEquals($arr['data']['company_user']['permissions'], 'create_client,create_invoice');

        $user = User::whereEmail('bob@good.ole.boys.co2.com')->first();

        $this->assertNotNull($user);

        $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($company2->id)->first();

        $this->assertNotNull($cu);

        /*Update the user permissions of this user*/
        $data = [
            'first_name' => 'Captain',
            'last_name' => 'Morgain',
            'email' => 'bob@good.ole.boys.co2.com',
            'company_user' => [
                'is_admin' => true,
                'is_owner' => false,
                'permissions' => 'create_invoice,create_invoice',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$this->encodePrimaryKey($user->id).'?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
        $this->assertTrue($arr['data']['company_user']['is_admin']);
        $this->assertFalse($arr['data']['company_user']['is_owner']);
        $this->assertEquals($arr['data']['company_user']['permissions'], 'create_invoice,create_invoice');
    }

    public function testPurgeUserTransfersEntities()
    {
        // Create account and owner user
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $owner_user = User::factory()->create([
            'account_id' => $account->id,
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
        ]);

        $settings = CompanySettings::defaults();

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $owner_user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        // Create secondary user to be purged
        $secondary_user = User::factory()->create([
            'account_id' => $account->id,
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
        ]);

        $secondary_user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 0,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        // Create a client owned by secondary user
        $client = \App\Models\Client::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'assigned_user_id' => $secondary_user->id,
        ]);

        // Create client contact
        $client_contact = \App\Models\ClientContact::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'is_primary' => true,
        ]);

        // Create invoice owned by secondary user
        $invoice = \App\Models\Invoice::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'assigned_user_id' => $secondary_user->id,
            'status_id' => \App\Models\Invoice::STATUS_DRAFT,
        ]);
        $invoice = $invoice->service()->createInvitations()->markSent()->save();

        // Create credit owned by secondary user
        $credit = \App\Models\Credit::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'assigned_user_id' => $secondary_user->id,
            'status_id' => \App\Models\Credit::STATUS_DRAFT,
        ]);

        $credit = $credit->service()->createInvitations()->markSent()->save();

        // Create quote owned by secondary user
        $quote = \App\Models\Quote::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'assigned_user_id' => $secondary_user->id,
            'status_id' => \App\Models\Quote::STATUS_DRAFT,
        ]);
        $quote = $quote->service()->createInvitations()->markSent()->save();

        // Create recurring invoice owned by secondary user
        $recurring_invoice = \App\Models\RecurringInvoice::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'assigned_user_id' => $secondary_user->id,
            'status_id' => \App\Models\RecurringInvoice::STATUS_DRAFT,
        ]);

        $recurring_invoice = $recurring_invoice->service()->createInvitations()->start()->save();
        // Create expense owned by secondary user
        $expense = \App\Models\Expense::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'assigned_user_id' => $secondary_user->id,
        ]);

        // Create task owned by secondary user
        $task = \App\Models\Task::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'assigned_user_id' => $secondary_user->id,
        ]);

        // Create vendor owned by secondary user
        $vendor = \App\Models\Vendor::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'assigned_user_id' => $secondary_user->id,
        ]);

        // Create vendor contact
        $vendor_contact = \App\Models\VendorContact::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'is_primary' => true,
        ]);

        // Create product owned by secondary user
        $product = \App\Models\Product::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'assigned_user_id' => $secondary_user->id,
        ]);

        // Create project owned by secondary user
        $project = \App\Models\Project::factory()->create([
            'user_id' => $secondary_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'assigned_user_id' => $secondary_user->id,
        ]);

        // Create an entity owned by owner but assigned to secondary user
        $invoice_assigned_only = \App\Models\Invoice::factory()->create([
            'user_id' => $owner_user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'assigned_user_id' => $secondary_user->id,
        ]);


        $invoice = $invoice->load('invitations');

        $this->assertCount(1, $invoice->invitations);
        $this->assertCount(1, $recurring_invoice->invitations);
        // Store IDs for later assertions
        $secondary_user_id = $secondary_user->id;
        $client_id = $client->id;
        $client_contact_id = $client_contact->id;
        $invoice_id = $invoice->id;
        $invoice_invitation_id = $invoice->invitations()->first()->id;
        $credit_id = $credit->id;
        $credit_invitation_id = $credit->invitations()->first()->id;
        $quote_id = $quote->id;
        $quote_invitation_id = $quote->invitations()->first()->id;
        $recurring_invoice_id = $recurring_invoice->id;
        $expense_id = $expense->id;
        $task_id = $task->id;
        $vendor_id = $vendor->id;
        $vendor_contact_id = $vendor_contact->id;
        $product_id = $product->id;
        $project_id = $project->id;
        $invoice_assigned_only_id = $invoice_assigned_only->id;

        // Perform the purge
        $user_repo = new UserRepository();
        $user_repo->purge($secondary_user, $owner_user);

        // Assert secondary user is deleted
        $this->assertNull(User::find($secondary_user_id));

        // Assert all entities are now owned by owner user
        $client = \App\Models\Client::find($client_id);
        $this->assertEquals($owner_user->id, $client->user_id);
        $this->assertNull($client->assigned_user_id);

        // Assert client contact user_id updated
        $client_contact = \App\Models\ClientContact::find($client_contact_id);
        $this->assertEquals($owner_user->id, $client_contact->user_id);

        $invoice = \App\Models\Invoice::find($invoice_id);
        $this->assertEquals($owner_user->id, $invoice->user_id);
        $this->assertNull($invoice->assigned_user_id);

        // Assert invoice invitation user_id updated
        $invoice_invitation = \App\Models\InvoiceInvitation::find($invoice_invitation_id);
        $this->assertEquals($owner_user->id, $invoice_invitation->user_id);

        $credit = \App\Models\Credit::find($credit_id);
        $this->assertEquals($owner_user->id, $credit->user_id);
        $this->assertNull($credit->assigned_user_id);

        // Assert credit invitation user_id updated
        $credit_invitation = \App\Models\CreditInvitation::find($credit_invitation_id);
        $this->assertEquals($owner_user->id, $credit_invitation->user_id);

        $quote = \App\Models\Quote::find($quote_id);
        $this->assertEquals($owner_user->id, $quote->user_id);
        $this->assertNull($quote->assigned_user_id);

        // Assert quote invitation user_id updated
        $quote_invitation = \App\Models\QuoteInvitation::find($quote_invitation_id);
        $this->assertEquals($owner_user->id, $quote_invitation->user_id);

        $recurring_invoice = \App\Models\RecurringInvoice::find($recurring_invoice_id);
        $this->assertEquals($owner_user->id, $recurring_invoice->user_id);
        $this->assertNull($recurring_invoice->assigned_user_id);

        $expense = \App\Models\Expense::find($expense_id);
        $this->assertEquals($owner_user->id, $expense->user_id);
        $this->assertNull($expense->assigned_user_id);

        $task = \App\Models\Task::find($task_id);
        $this->assertEquals($owner_user->id, $task->user_id);
        $this->assertNull($task->assigned_user_id);

        $vendor = \App\Models\Vendor::find($vendor_id);
        $this->assertEquals($owner_user->id, $vendor->user_id);
        $this->assertNull($vendor->assigned_user_id);

        // Assert vendor contact user_id updated
        $vendor_contact = \App\Models\VendorContact::find($vendor_contact_id);
        $this->assertEquals($owner_user->id, $vendor_contact->user_id);

        $product = \App\Models\Product::find($product_id);
        $this->assertEquals($owner_user->id, $product->user_id);
        $this->assertNull($product->assigned_user_id);

        $project = \App\Models\Project::find($project_id);
        $this->assertEquals($owner_user->id, $project->user_id);
        $this->assertNull($project->assigned_user_id);

        // Assert entity owned by owner but assigned to secondary now has null assigned_user_id
        $invoice_assigned_only = \App\Models\Invoice::find($invoice_assigned_only_id);
        $this->assertEquals($owner_user->id, $invoice_assigned_only->user_id);
        $this->assertNull($invoice_assigned_only->assigned_user_id);
    }
}
