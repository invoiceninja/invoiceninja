<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration;

use App\Factory\CompanyUserFactory;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Libraries\MultiDB
 *
 * Proves that we can reliably switch database connections at runtime
 */
class MultiDBUserTest extends TestCase
{
    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutExceptionHandling();

        if (! config('ninja.db.multi_db_enabled')) {
            $this->markTestSkipped('Multi DB not enabled - skipping');
        }

        User::unguard();

        $ac = Account::factory()->make();

        $ac->setHidden(['hashed_id']);

        $account = Account::on('db-ninja-01')->create($ac->toArray());
        $account2 = Account::on('db-ninja-02')->create($ac->toArray());

        $company = Company::factory()->make([
            'account_id' => $account->id,
        ]);

        $company2 = Company::factory()->make([
            'account_id' => $account2->id,
        ]);

        $company->setHidden(['settings', 'settings_object', 'hashed_id']);
        $company2->setHidden(['settings', 'settings_object', 'hashed_id']);

        $coco = Company::on('db-ninja-01')->create($company->toArray());

        $coco2 = Company::on('db-ninja-02')->create($company2->toArray());

        $user = [
            'account_id' => $account->id,
            'first_name' => 'user_db_1',
            'last_name' => 'user_db_1-s',
            'phone' => '55555',
            'email_verified_at' => now(),
            'password' => Hash::make('ALongAndBriliantPassword'), // secret
            'remember_token' => \Illuminate\Support\Str::random(10),
            'email' => 'db1@example.com',
            'oauth_user_id' => '123',
            //     'account_id' => $account->id,
        ];

        $user2 = [
            'account_id' => $account2->id,
            'first_name'        => 'user_db_2',
            'last_name'         => 'user_db_2-s',
            'phone'             => '55555',
            'email_verified_at' => now(),
            'password' => 'ALongAndBriliantPassword', // secret
            'remember_token'    => \Illuminate\Support\Str::random(10),
            'email'             => 'db2@example.com',
            'oauth_user_id'     => 'abc',
            //      'account_id' => $account2->id,

        ];

        $user = User::on('db-ninja-01')->create($user);

        // $cu = CompanyUserFactory::create($user->id, $coco->id, $account->id);
        // $cu->is_owner = true;
        // $cu->is_admin = true;
        // $cu->setConnection('db-ninja-01');
        // $cu->save();

        CompanyUser::on('db-ninja-01')->create([
            'company_id' => $coco->id,
            'account_id' => $account->id,
            'user_id' => $user->id,
            'is_owner' => true,
            'is_admin' => true,
        ]);

        $user2 = User::on('db-ninja-02')->create($user2);

        CompanyUser::on('db-ninja-02')->create([
            'company_id' => $coco2->id,
            'account_id' => $account2->id,
            'user_id' => $user2->id,
            'is_owner' => true,
            'is_admin' => true,
        ]);

        $this->token = \Illuminate\Support\Str::random(40);

        $this->company_token = CompanyToken::on('db-ninja-01')->create([
            'user_id' => $user->id,
            'company_id' => $coco->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => $this->token,
        ]);

        User::unguard(false);
    }

    public function test_oauth_user_db2_exists()
    {
        $user = MultiDB::hasUser(['email' => 'db2@example.com', 'oauth_user_id' => 'abc']);

        $this->assertEquals($user->email, 'db2@example.com');
    }

    public function test_oauth_user_db1_exists()
    {
        $user = MultiDB::hasUser(['email' => 'db1@example.com', 'oauth_user_id' => '123']);

        $this->assertEquals($user->email, 'db1@example.com');
    }

    public function test_check_user_exists()
    {
        $this->assertTrue(MultiDB::checkUserEmailExists('db1@example.com'));
    }

    public function test_check_user_does_not_exist()
    {
        $this->assertFalse(MultiDB::checkUserEmailExists('bademail@example.com'));
    }

    public function test_check_that_set_db_by_email_works()
    {
        $this->assertTrue(MultiDB::userFindAndSetDb('db1@example.com'));
    }

    public function test_check_that_set_db_by_email_works_db_2()
    {
        $this->assertTrue(MultiDB::userFindAndSetDb('db2@example.com'));
    }

    public function test_check_that_set_db_by_email_works_db_3()
    {
        $this->assertFalse(MultiDB::userFindAndSetDb('bademail@example.com'));
    }

    /*
     * This is what you do when you demand 100% code coverage :/
     */
    public function test_set_db_invokes()
    {
        $this->expectNotToPerformAssertions(MultiDB::setDB('db-ninja-01'));
    }

    public function test_cross_db_user_linking_fails_appropriately()
    {

    //$this->withoutExceptionHandling();

        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'db2@example.com',
            'company_user' => [
                'is_admin' => true,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            ])->post('/api/v1/users?include=company_user', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
            nlog($message);
        }

        if ($response) {
            $response->assertStatus(403);
        }
    }

    // public function test_cross_db_user_linking_succeeds_appropriately()
    // {
    //     $data = [
    //         'first_name' => 'hey',
    //         'last_name' => 'you',
    //         'email' => 'db1@example.com',
    //         'company_user' => [
    //                 'is_admin' => false,
    //                 'is_owner' => false,
    //                 'permissions' => 'create_client,create_invoice',
    //             ],
    //     ];

    //     try {
    //         $response = $this->withHeaders([
    //             'X-API-SECRET' => config('ninja.api_secret'),
    //             'X-API-TOKEN' => $this->token,
    //             'X-API-PASSWORD' => 'ALongAndBriliantPassword',
    //       ])->post('/api/v1/users?include=company_user', $data);
    //     } catch (ValidationException $e) {
    //         \Log::error('in the validator');
    //         $message = json_decode($e->validator->getMessageBag(), 1);
    //         \Log::error($message);
    //         $this->assertNotNull($message);
    //     }

    //     if ($response) {
    //         $response->assertStatus(200);
    //     }
    // }

    protected function tearDown() :void
    {
        DB::connection('db-ninja-01')->table('users')->delete();
        DB::connection('db-ninja-02')->table('users')->delete();

        config(['database.default' => config('ninja.db.default')]);
    }
}
