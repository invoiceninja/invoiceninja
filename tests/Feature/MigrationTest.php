<?php

namespace Feature;

use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\MigrationController
 */

class MigrationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    
        $this->makeTestData();
    }


    public function testCompanyExists()
    {

    	$co = Company::find($this->company->id);

    	// $this->assertNull($this->company);
    	$this->assertNotNull($co);
    }

    public function testThatCompanyDeletesCompletely()
    {
    	$company_id = $this->company->id;

    	$this->company->delete();
    	$this->company->fresh();

    	$co = Company::find($company_id);

    	// $this->assertNull($this->company);
    	$this->assertNull($co);

    }

    public function testCompanyChildDeletes()
    {

        $this->makeTestData();

        $this->assertNotNull($this->company);

    	$co = Client::whereCompanyId($this->company->id)->get();
    	$inv = Invoice::whereCompanyId($this->company->id)->get();

    	$this->assertEquals($co->count(),1);
    	$this->assertEquals($inv->count(),1);

		DB::statement( 'DELETE FROM `clients` WHERE `company_id`=:company_id', array('company_id' => $this->company->id) );

    	$co = Client::whereCompanyId($this->company->id)->get();
    	$inv = Invoice::whereCompanyId($this->company->id)->get();

    	$this->assertEquals($co->count(),0);
    	$this->assertEquals($inv->count(),0);

    	$this->assertNotNull($this->company);
    	$this->assertNotNull($this->company->settings);
    	$this->assertNotNull($this->company->settings->timezone_id);

    }

    public function testMigrationFileUpload()
    {
        $data = [];

        $token = $this->company->tokens->first()->token;

        $response = $this->withHeaders([
                'X-API-TOKEN' => $token,
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-Requested-With' => 'XMLHttpRequest'
            ])->post('/api/v1/migration/upload_migration', $data);

        dd($response->getContent()); // "{"message":"Access denied","errors":[]}"

        $response->assertStatus(200);
        $this->assertTrue(file_exists(base_path('migrations/migration/migration.json')));
    }
}