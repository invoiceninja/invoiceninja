<?php

namespace Tests\Integration;

use App\Http\ValidationRules\NewUniqueUserRule;
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
 * @covers  App\Http\ValidationRules\NewUniqueUserRule
 */
class UniqueEmailTest extends TestCase
{
    protected $rule;

    public function setUp() :void
    {
        parent::setUp();

        if (! config('ninja.db.multi_db_enabled'))
            $this->markTestSkipped('Multi DB not enabled - skipping');

         DB::connection('db-ninja-01')->table('users')->delete();
         DB::connection('db-ninja-02')->table('users')->delete();

        $this->rule = new NewUniqueUserRule();

        $ac = factory(\App\Models\Account::class)->make();

        $account = Account::on('db-ninja-01')->create($ac->toArray());
        $account2 = Account::on('db-ninja-02')->create($ac->toArray());

        $company = factory(\App\Models\Company::class)->make([
            'account_id' => $account->id,
                        'domain' => 'ninja.test',


        ]);

        $company2 = factory(\App\Models\Company::class)->make([
            'account_id' => $account2->id,
                        'domain' => 'ninja.test',

        ]);

        $company->setHidden(['settings', 'settings_object']);
        $company2->setHidden(['settings', 'settings_object']);

        Company::on('db-ninja-01')->create($company->toArray());
        Company::on('db-ninja-02')->create($company2->toArray());


        $user = [
            'first_name' => 'user_db_1',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
           // 'account_id' => $account->id,
        ];

        $user2 = [
            'first_name' => 'user_db_2',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
          //  'account_id' => $account2->id,
        ];

        User::on('db-ninja-01')->create($user);
        User::on('db-ninja-02')->create($user2);

    }

    public function test_unique_emails_detected_on_database()
    {

        $this->assertFalse($this->rule->passes('email', 'user@example.com'));

    }

    public function test_no_unique_emails_detected()
    {

        $this->assertTrue($this->rule->passes('email', 'nohit@example.com'));

    }

    public function tearDown() :void
    {
        DB::connection('db-ninja-01')->table('users')->delete();
        DB::connection('db-ninja-02')->table('users')->delete();
    }

}