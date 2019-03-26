<?php

namespace Tests\Feature;

use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\Client;
use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class ClientTest extends TestCase
{

    use DatabaseTransactions;

    public function setUp()
    {
        parent::setUp();
        Session::start();

        $faker = \Faker\Factory::create();

        $this->data = [
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => $faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token()
        ];

       // $this->user = CreateAccount::dispatchNow($data);
    }

    public function testAccountCreation()
    {
        $response = $this->post('/signup', $this->data);

        $this->assertEquals($response->json(), 'yadda');
        //$response->assertSuccessful();
        //$response->assertStatus(200);

    }

    public function testUserCreated()
    {
        $this->assertTrue(true);
    }



}
