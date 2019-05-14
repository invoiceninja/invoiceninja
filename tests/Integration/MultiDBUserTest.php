<?php

namespace Tests\Integration;

use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Libraries\MultiDB
 *
 * Proves that we can reliably switch database connections at runtime
 *
 */

class MultiDBUserTest extends TestCase
{

    public function setUp() :void
    {
        parent::setUp();

        if (! config('ninja.db.multi_db_enabled'))
            $this->markTestSkipped('Multi DB not enabled - skipping');

        User::unguard();

        $ac = factory(\App\Models\Account::class)->make();

        $account = Account::on('db-ninja-01')->create($ac->toArray());
        $account2 = Account::on('db-ninja-02')->create($ac->toArray());

        $company = factory(\App\Models\Company::class)->make([
            'account_id' => $account->id,
        ]);

        $company2 = factory(\App\Models\Company::class)->make([
            'account_id' => $account2->id,
        ]);


        $company->setHidden(['settings', 'settings_object']);
        $company2->setHidden(['settings', 'settings_object']);
        
        Company::on('db-ninja-01')->create($company->toArray());
        Company::on('db-ninja-02')->create($company2->toArray());

        $user = [
            'first_name' => 'user_db_1',
            'last_name' => 'user_db_1-s',
            'phone' => '55555',
            'email_verified_at' => now(),
            'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
            'remember_token' => str_random(10),
            'email' => 'db1@example.com',
            'oauth_user_id' => '123',
       //     'account_id' => $account->id,
        ];


        $user2 = [
            'first_name'        => 'user_db_2',
            'last_name'         => 'user_db_2-s',
            'phone'             => '55555',
            'email_verified_at' => now(),
            'password'          => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
            'remember_token'    => str_random(10),
            'email'             => 'db2@example.com',
            'oauth_user_id'     => 'abc',
      //      'account_id' => $account2->id,

        ];

        User::on('db-ninja-01')->create($user);
        User::on('db-ninja-02')->create($user2);
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

    /*
     * This is what you do when you demand 100% code coverage :/
     */

    public function test_set_db_invokes()
    {
        $this->expectNotToPerformAssertions(MultiDB::setDB('db-ninja-01'));
    }

    public function tearDown() :void
    {
         DB::connection('db-ninja-01')->table('users')->delete();
         DB::connection('db-ninja-02')->table('users')->delete();
    }
}
