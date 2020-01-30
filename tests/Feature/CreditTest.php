<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Credit;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Model;

class CreditTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    public function testCreditsList()
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

        $this->assertNotNull($company_token);
        $this->assertNotNull($token);
        $this->assertNotNull($user);
        $this->assertNotNull($company);

        factory(Client::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company) {

            factory(\App\Models\ClientContact::class, 1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class, 1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);
        });

        $client = Client::all()->first();

        factory(Credit::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->get('/api/v1/credits');

        $response->assertStatus(200);
    }

    public function testCreditRESTEndPoints()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits/' . $this->encodePrimaryKey($this->credit->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits/' . $this->encodePrimaryKey($this->credit->id) . '/edit');

        $response->assertStatus(200);

        $credit_update = [
            'tax_name1' => 'dippy',
        ];

        $this->assertNotNull($this->credit);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/credits/' . $this->encodePrimaryKey($this->credit->id), $credit_update)
            ->assertStatus(200);
    }

    public function testPostNewCredit()
    {
        $credit = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/', $credit)
            ->assertStatus(200);
    }

    public function testDeleteCredit()
    {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->delete('/api/v1/credits/'.$this->encodePrimaryKey($this->credit->id));

        $response->assertStatus(200);
    }
}
