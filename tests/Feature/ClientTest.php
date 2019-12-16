<?php

namespace Tests\Feature;

use App\DataMapper\DefaultSettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ClientController
 */
class ClientTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

    }

    public function testClientList()
    {

        $data = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup?include=account', $data);


        $response->assertStatus(200);

        $acc = $response->json();


        $account = Account::find($this->decodePrimaryKey($acc['data'][0]['account']['id']));

        $token = $account->default_company->tokens->first()->token;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/clients');

        $response->assertStatus(200);

    }

    /*
     * @covers ClientController
     */
    public function testClientRestEndPoints()
    {

        $data = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup?include=account', $data);

        $acc = $response->json();

        $account = Account::find($this->decodePrimaryKey($acc['data'][0]['account']['id']));

        $company_token = $account->default_company->tokens()->first();

        $token = $company_token->token;
        $company = $company_token->company;

        $user = $company_token->user;

        //$company_user = $company->company_users()->first();

        //$user = User::find($company_user->user_id);
        $this->assertNotNull($company_token);
        $this->assertNotNull($token);
        $this->assertNotNull($user);
        $this->assertNotNull($company);
        //$this->assertNotNull($user->token->company);

        factory(\App\Models\Client::class, 3)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,2)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);

        });

        $client = $account->default_company->clients()->first();
        $client->load('contacts');


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/clients/'.$this->encodePrimaryKey($client->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/clients/'.$this->encodePrimaryKey($client->id).'/edit');

        $response->assertStatus(200);

        $client_update = [
            'name' => 'A Funky Name'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->put('/api/v1/clients/'.$this->encodePrimaryKey($client->id), $client_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->delete('/api/v1/clients/'.$this->encodePrimaryKey($client->id));

        $response->assertStatus(200);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->post('/api/v1/clients/', ['name' => 'New Client'])
            ->assertStatus(200);


        $response->assertStatus(200);


        }

        public function testDefaultTimeZoneFromClientModel()
        {

        $account = factory(\App\Models\Account::class)->create();
                $company = factory(\App\Models\Company::class)->create([
                    'account_id' => $account->id,
                     ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = factory(\App\Models\User::class)->create([
        //    'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default'))
        ]);


        $userPermissions = collect([
                                    'view_invoice',
                                    'view_client',
                                    'edit_client',
                                    'edit_invoice',
                                    'create_invoice',
                                    'create_client'
                                ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

            factory(\App\Models\Client::class, 3)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){

                factory(\App\Models\ClientContact::class,1)->create([
                    'user_id' => $user->id,
                    'client_id' => $c->id,
                    'company_id' => $company->id,
                    'is_primary' => 1,
                ]);

                factory(\App\Models\ClientContact::class,2)->create([
                    'user_id' => $user->id,
                    'client_id' => $c->id,
                    'company_id' => $company->id
                ]);

            });

            $client = Client::whereUserId($user->id)->whereCompanyId($company->id)->first();

            $this->assertNotNull($client);

            /* Make sure we have a valid settings object*/
            $this->assertEquals($client->getSetting('timezone_id'), 1);

            /* Make sure we are harvesting valid data */
            $this->assertEquals($client->timezone()->name, 'Pacific/Midway');

            /* Make sure NULL settings return the correct count (0) instead of throwing an exception*/
            $this->assertEquals($client->contacts->count(), 3);
        }

    /** @test */
    public function testMassivelyCreatingClients()
    {
        $data = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
        ])->post('/api/v1/signup?include=account', $data);

        $response->assertStatus(200);

        $acc = $response->json();

        $account = Account::find($this->decodePrimaryKey($acc['data'][0]['account']['id']));

        $token = $account->default_company->tokens->first()->token;

        $body = [
            'action' => 'create',
            'clients' => [
                ['name' => $this->faker->firstName, 'website' => 'my-awesome-website-1.com'],
                ['name' => $this->faker->firstName, 'website' => 'my-awesome-website-2.com'],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->post(route('clients.bulk'), $body);

        $response->assertStatus(200);

        $first_record = Client::where('website', 'my-awesome-website-1.com')->first();
        $second_record = Client::where('website', 'my-awesome-website-2.com')->first();

        $this->assertNotNull($first_record);
        $this->assertNotNull($second_record);
    }
}
