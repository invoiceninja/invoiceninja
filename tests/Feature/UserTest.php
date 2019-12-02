<?php

namespace Tests\Feature;


use App\Factory\UserFactory;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
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

        $this->makeTestData();
    }

    public function testUserList()
    {


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/users');

        $response->assertStatus(200);

    }

    public function testUserStore()
    {
        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob@good.ole.boys.com',
            'company_user' => [
                    'is_admin' => false,
                    'is_owner' => false,
                    'permissions' => 'create_client,create_invoice'
                ],
        ];

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

    }

    public function testUserAttachAndDetach()
    {
        $user = UserFactory::create();
        $user->first_name = 'Test';
        $user->last_name = 'Palloni';
        $user->save();

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/users/'.$this->encodePrimaryKey($user->id).'/attach_to_company?include=company_user');

        $response->assertStatus(200);

        $this->assertNotNull($user->company_user);
        $this->assertEquals($user->company_user->company_id, $this->company->id);


            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->delete('/api/v1/users/'.$this->encodePrimaryKey($user->id).'/detach_from_company?include=company_user');

        $response->assertStatus(200);


        $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($this->company->id)->first();
        $ct = CompanyToken::whereUserId($user->id)->whereCompanyId($this->company->id)->first();

        $this->assertNull($cu);
        $this->assertNull($ct);
        $this->assertNotNull($user);

    }
}