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
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ClientApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }


    public function testClientPost()
    {
        $data = [
            'name' => $this->faker->firstName,
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->post('/api/v1/clients', $data);


        $response->assertStatus(200);
    }

    public function testClientPut()
    {
        $data = [
            'name' => $this->faker->firstName,
            'id_number' => 'Coolio',
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->put('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), $data);


        $response->assertStatus(200);
    }

    public function testClientGet()
    {


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->get('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id));


        $response->assertStatus(200);

    }
    
}