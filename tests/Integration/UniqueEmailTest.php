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

use App\Http\ValidationRules\NewUniqueUserRule;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 
 *   App\Http\ValidationRules\NewUniqueUserRule
 */
class UniqueEmailTest extends TestCase
{
    use DatabaseTransactions;

    protected $rule;

    protected function setUp(): void
    {
        parent::setUp();

        User::unguard();

        if (! config('ninja.db.multi_db_enabled')) {
            $this->markTestSkipped('Multi DB not enabled - skipping');
        }

        $this->rule = new NewUniqueUserRule();

        $ac = Account::factory()->make();
        $ac->setHidden(['hashed_id']);

        $account = Account::on('db-ninja-01')->create($ac->toArray());

        $company = Company::factory()->make([
            'account_id' => $account->id,
        ]);

        $company->setHidden(['settings', 'settings_object', 'hashed_id']);

        Company::on('db-ninja-01')->create($company->toArray());

        $ac2 = Account::factory()->make();
        $ac2->setHidden(['hashed_id']);
        $account2 = Account::on('db-ninja-02')->create($ac2->toArray());

        $company2 = Company::factory()->make([
            'account_id' => $account2->id,
        ]);

        $company2->setHidden(['settings', 'settings_object', 'hashed_id']);

        Company::on('db-ninja-02')->create($company2->toArray());

        $user = [
            'first_name' => 'user_db_1',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'account_id' => $account->id,
        ];

        $user2 = [
            'first_name' => 'user_db_2',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'account_id' => $account2->id,
        ];

        $user_find = User::on('db-ninja-01')->where('email', 'user@example.com')->first();

        if (! $user_find) {
            User::on('db-ninja-01')->create($user);
        }

        $user_find = User::on('db-ninja-02')->where('email', 'user@example.com')->first();

        if (! $user_find) {
            User::on('db-ninja-02')->create($user2);
        }
    }

    public function test_unique_emails_detected_on_database()
    {
        $this->assertFalse($this->rule->passes('email', 'user@example.com'));
    }

    public function test_no_unique_emails_detected()
    {
        $this->assertTrue($this->rule->passes('email', 'nohit@example.com'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        DB::connection('db-ninja-01')->table('users')->delete();
        DB::connection('db-ninja-02')->table('users')->delete();
    }
}
