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
 *
 *   App\Libraries\MultiDB
 *
 * Proves that we can reliably switch database connections at runtime
 */
class MultiDBUserTest extends TestCase
{
    protected $token;
    protected $company_token;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('ninja.db.multi_db_enabled')) {
            $this->markTestSkipped('Multi DB not enabled - skipping');
        }

        foreach (MultiDB::getDBs() as $db) {
            MultiDB::setDB($db);
            $u = User::where('email', 'db1@example.com')->first();
            if ($u) {
                $u->account->delete();
            }


            $u = User::where('email', 'db2@example.com')->first();
            if ($u) {
                $u->account->delete();
            }
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

        /** @var CompanyToken $company_token */
        $company_token = CompanyToken::on('db-ninja-01')->create([
            'user_id' => $user->id,
            'company_id' => $coco->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => $this->token,
        ]);

        $this->company_token = $company_token;
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

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data);


        $response->assertStatus(422);

    }

    protected function tearDown(): void
    {
        try {
            // Clean up database records before calling parent::tearDown()
            $this->cleanupTestData();
        } catch (\Exception $e) {
            // Log error but don't fail teardown
            error_log("Error during test cleanup: " . $e->getMessage());
        }

        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        // Only proceed if we have database connections available
        if (!app()->bound('db') || !config('database.connections.db-ninja-01')) {
            return;
        }

        try {
            // Clean up db-ninja-01
            if (\DB::connection('db-ninja-01')->getPdo()) {
                $u = User::on('db-ninja-01')->where('email', 'db1@example.com')->first();
                if ($u && $u->account) {
                    $u->account->delete();
                }

                $u = User::on('db-ninja-01')->where('email', 'db2@example.com')->first();
                if ($u && $u->account) {
                    $u->account->delete();
                }
            }

            // Clean up db-ninja-02
            if (\DB::connection('db-ninja-02')->getPdo()) {
                $u = User::on('db-ninja-02')->where('email', 'db1@example.com')->first();
                if ($u && $u->account) {
                    $u->account->delete();
                }

                $u = User::on('db-ninja-02')->where('email', 'db2@example.com')->first();
                if ($u && $u->account) {
                    $u->account->delete();
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail cleanup
            error_log("Error during database cleanup: " . $e->getMessage());
        }
    }
}
