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
use App\DataMapper\DefaultSettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ClientController
 */
class ClientTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->withoutExceptionHandling();

        Client::reguard();
        ClientContact::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    public function testStoreClientUsingCountryCode()
    {
        $data = [
            'name' => 'Country Code Name',
            'country_code' => 'US',
            'currency_code' => 'USD',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $arr = $response->json();
        $client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(840, $client->country_id);
        $this->assertEquals(1, $client->settings->currency_id);

        $data = [
            'name' => 'Country Code Name',
            'country_code' => 'USA',
            'currency_code' => 'USD',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $arr = $response->json();
        $client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(840, $client->country_id);
        $this->assertEquals(1, $client->settings->currency_id);

        $data = [
            'name' => 'Country Code Name',
            'country_code' => 'AU',
            'currency_code' => 'AUD',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $arr = $response->json();
        $client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(36, $client->country_id);
        $this->assertEquals(12, $client->settings->currency_id);
    }

    public function testClientList()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients');

        $response->assertStatus(200);
    }

    /*
     * @covers ClientController
     */
    public function testClientRestEndPoints()
    {
        Client::factory()->count(3)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->count(2)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
            ]);
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id).'/edit');

        $response->assertStatus(200);

        $client_update = [
            'name' => 'A Funky Name',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), $client_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', ['name' => 'New Client'])
            ->assertStatus(200);

        $response->assertStatus(200);

        $this->client->is_deleted = true;
        $this->client->save();

        $client_update = [
            'name' => 'Double Funk',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), $client_update)
            ->assertStatus(400);
    }

    public function testDefaultTimeZoneFromClientModel()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        Client::factory()->count(3)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company) {
            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->count(2)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
            ]);
        });

        $this->client = Client::whereUserId($user->id)->whereCompanyId($company->id)->first();

        $this->assertNotNull($this->client);

        /* Make sure we have a valid settings object*/
        $this->assertEquals($this->client->getSetting('timezone_id'), 1);

        /* Make sure we are harvesting valid data */
        $this->assertEquals($this->client->timezone()->name, 'Pacific/Midway');

        /* Make sure NULL settings return the correct count (0) instead of throwing an exception*/
        $this->assertEquals($this->client->contacts->count(), 3);
    }

    public function testClientCreationWithIllegalContactObject()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',

        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => '',
            'settings' => '',
            'is_locked' => 0,
        ]);

        $company_token = new CompanyToken;
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = $user->first_name.' '.$user->last_name;
        $company_token->token = Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        $this->token = $company_token->token;

        $data = [
            'name' => 'A loyal Client',
            'contacts' => $this->faker->unique()->safeEmail(),
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }
    }

    public function testCreatingClientAndContacts()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',

        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => '',
            'settings' => '',
            'is_locked' => 0,
        ]);

        $company_token = new CompanyToken;
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = $user->first_name.' '.$user->last_name;
        $company_token->token = Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $this->token = $company_token->token;

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                ['email' => $this->faker->unique()->safeEmail()],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data)
                ->assertStatus(200);

        // $arr = $response->json();

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '*****',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data)
                ->assertStatus(200);

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1Qajsj...33',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        $response->assertStatus(200);

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1Qajsj...33',
                ],
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1234AAAAAaaaaa',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client_id = $arr['data']['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->client_id, $data)->assertStatus(200);

        $arr = $response->json();

        $safe_email = $this->faker->unique()->safeEmail();

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $safe_email,
                    'password' => '',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $contact = $this->client->contacts()->whereEmail($safe_email)->first();

        $this->assertEquals(0, strlen($contact->password));

        $safe_email = $this->faker->unique()->safeEmail();

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $safe_email,
                    'password' => 'AFancyDancy191$Password',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $contact = $this->client->contacts()->whereEmail($safe_email)->first();

        $this->assertGreaterThan(1, strlen($contact->password));

        $password = $contact->password;

        $data = [
            'name' => 'A Stary eyed client',
            'contacts' => [
                [
                    'id' => $contact->hashed_id,
                    'email' => $safe_email,
                    'password' => '*****',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/clients/'.$this->client->hashed_id, $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client = Client::find($this->decodePrimaryKey($arr['data']['id']));
        $this->client->fresh();

        $contact = $this->client->contacts()->whereEmail($safe_email)->first();

        $this->assertEquals($password, $contact->password);
    }
}
