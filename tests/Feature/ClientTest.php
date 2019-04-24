<?php

namespace Tests\Feature;

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

    public function setUp()
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
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup', $data);


        $response->assertStatus(200);

        $acc = $response->json();


        $account = Account::find($this->decodePrimaryKey($acc['data']['id']));

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
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup', $data);

        $acc = $response->json();

        $account = Account::find($this->decodePrimaryKey($acc['data']['id']));        

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
        $this->assertNotNull($user->tokens->first()->company);

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

            $user = User::all()->first();
            $company = Company::all()->first();

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

            $client = Client::all()->first();

            /* Make sure we have a valid settings object*/
            $this->assertEquals($client->getSettings()->timezone_id, 15);            

            /* Make sure we are harvesting valid data */
            $this->assertEquals($client->timezone()->name, 'US/Eastern');

            $contacts = ClientContact::whereIn('id', explode(',', $client->getSettings()->invoice_email_list))->get();

            /* Make sure NULL settings return the correct count (0) instead of throwing an exception*/
            $this->assertEquals(count($contacts), 0);
        }

}
