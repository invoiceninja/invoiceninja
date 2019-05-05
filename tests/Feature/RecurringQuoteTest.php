<?php

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\RecurringQuote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\RecurringQuoteController
 */
    
class RecurringQuoteTest extends TestCase
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

    public function testRecurringQuoteList()
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

        $this->assertNotNull($company_token);
        $this->assertNotNull($token);
        $this->assertNotNull($user);
        $this->assertNotNull($company);
        $this->assertNotNull($user->tokens->first()->company);

        factory(\App\Models\Client::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);

        });
        $client = Client::all()->first();

        factory(\App\Models\RecurringQuote::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/recurring_quotes');

        $response->assertStatus(200);

    }

    public function testRecurringQuoteRESTEndPoints()
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

        $this->assertNotNull($company_token);
        $this->assertNotNull($token);
        $this->assertNotNull($user);
        $this->assertNotNull($company);
        $this->assertNotNull($user->tokens->first()->company);

        factory(\App\Models\Client::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);

        });
        $client = Client::all()->first();

        factory(\App\Models\RecurringQuote::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $RecurringQuote = RecurringQuote::where('user_id',$user->id)->first();
        $RecurringQuote->settings = $client->getMergedSettings();
        $RecurringQuote->save();

        
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/recurring_quotes/'.$this->encodePrimaryKey($RecurringQuote->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/recurring_quotes/'.$this->encodePrimaryKey($RecurringQuote->id).'/edit');

        $response->assertStatus(200);

        $RecurringQuote_update = [
            'status_id' => RecurringQuote::STATUS_DRAFT
        ];

        $this->assertNotNull($RecurringQuote);
        $this->assertNotNull($RecurringQuote->settings);

        $this->assertTrue(property_exists($RecurringQuote->settings, 'custom_taxes1'));

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->put('/api/v1/recurring_quotes/'.$this->encodePrimaryKey($RecurringQuote->id), $RecurringQuote_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->delete('/api/v1/recurring_quotes/'.$this->encodePrimaryKey($RecurringQuote->id));

        $response->assertStatus(200);

    }

}
