<?php

namespace Tests\Unit;

use App\Http\ValidationRules\UniqueUserRule;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\NumberHelper
 */
class UniqueEmailTest extends TestCase
{
    use InteractsWithDatabase;
    //use DatabaseMigrations;

    protected $rule;

    public function setUp()
    {
        parent::setUp();

        if (! config('ninja.db.multi_db_enabled'))
            $this->markTestSkipped('Multi DB not enabled - skipping');

         DB::connection('db-ninja-1')->table('users')->delete();
         DB::connection('db-ninja-2')->table('users')->delete();

        $this->rule = new UniqueUserRule();

        $account = factory(\App\Models\Account::class)->create();

        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
        ]);


        $user = [
            'first_name' => 'user_db_1',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'db' => config('database.default'),
            'account_id' => $account->id,
        ];

        User::on('db-ninja-1')->create($user);
        User::on('db-ninja-2')->create($user);

    }

    public function test_unique_emails_detected_on_database()
    {

        $this->assertFalse($this->rule->passes('email', 'user@example.com'));

    }

    public function test_no_unique_emails_detected()
    {

        $this->assertTrue($this->rule->passes('email', 'nohit@example.com'));

    }

    public function tearDown()
    {
        DB::connection('db-ninja-1')->table('users')->delete();
        DB::connection('db-ninja-2')->table('users')->delete();
    }

}