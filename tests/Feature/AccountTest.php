<?php

namespace Tests\Feature;

use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\Client;
use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\AccountController
 */

class AccountTest extends TestCase
{
    //use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for CI Testing');
        }
    }

    public function testApiAccountCreation()
    {

        Account::all()->each(function($account) {
            $account->delete();
        });


        $data = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup?include=account', $data);

        $response->assertStatus(200);
    }
}
