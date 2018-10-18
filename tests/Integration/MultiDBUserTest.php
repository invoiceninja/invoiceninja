<?php

namespace Tests\Unit;

use App\Libraries\MultiDB;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
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
    //use DatabaseMigrations;
    use InteractsWithDatabase;

    public function setUp()
    {
        parent::setUp();

        if (config('auth.providers.users.driver') == 'eloquent')
            $this->markTestSkipped('Multi DB not enabled - skipping');

        User::unguard();

        $user = [
            'first_name'        => 'user_db_1',
            'last_name'         => 'user_db_1-s',
            'phone'             => '55555',
            'email_verified_at' => now(),
            'password'          => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
            'remember_token'    => str_random(10),
            'email'             => 'db1@example.com',
            'oauth_user_id'        => '123'
        ];


        $user2 = [
            'first_name'        => 'user_db_2',
            'last_name'         => 'user_db_2-s',
            'phone'             => '55555',
            'email_verified_at' => now(),
            'password'          => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
            'remember_token'    => str_random(10),
            'email'             => 'db2@example.com',
            'oauth_user_id'     => 'abc'
        ];

        User::on('db-ninja-1')->create($user);
        User::on('db-ninja-2')->create($user2);
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
        $this->expectNotToPerformAssertions(MultiDB::setDB('db-ninja-1'));
    }

    public function tearDown()
    {
         DB::connection('db-ninja-1')->table('users')->delete();
         DB::connection('db-ninja-2')->table('users')->delete();
    }
}
