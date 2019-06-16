<?php

namespace Tests\Feature;


use App\Models\Account;
use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
* @test
 * @covers App\Http\Controllers\UserController
*/
class UserTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

    }

    public function testUserList()
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
            ])->post('/api/v1/signup', $data);


        $response->assertStatus(200);

        $acc = $response->json();


        $account = Account::find($this->decodePrimaryKey($acc['data']['id']));

        $token = $account->default_company->tokens->first()->token;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/users');

        $response->assertStatus(200);

    }


}