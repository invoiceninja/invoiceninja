<?php

namespace Tests\Unit;

use App\Http\ValidationRules\UniqueUserRule;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
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

    protected $rule;

    public function setUp()
    {
        parent::setUp();

        $this->rule = new UniqueUserRule();
    }

    public function test_unique_emails_detected_on_database()
    {
        if (config('auth.providers.users.driver') == 'eloquent')
            $this->markTestSkipped('Multi DB not enabled - skipping');

        $user = [
            'first_name' => 'user_db_1',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
        ];

        User::on('db-ninja-1')->create($user);
        User::on('db-ninja-2')->create($user);


        $this->assertFalse($this->rule->passes('unique_email', 'user@example.com'));
    }

    public function tearDown()
    {
        DB::connection('db-ninja-1')->table('users')->delete();
        DB::connection('db-ninja-2')->table('users')->delete();
    }

}