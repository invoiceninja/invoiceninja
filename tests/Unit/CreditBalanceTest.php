<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Unit;

use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\User;
use Tests\TestCase;

/**
 * @test
 */
class CreditBalanceTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();
        $this->faker = \Faker\Factory::create();
    }

    public function testCreditBalance()
    {

        $account = Account::factory()->create();
        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => $this->faker->safeEmail]
        );

        $company = Company::factory()->create(['account_id' => $account->id]);
        $client = Client::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);

        $credit = Credit::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'balance' => 10,
            'number' => 'testing-number-01',
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->assertEquals($client->service()->getCreditBalance(), 10);
    }


    public function testExpiredCreditBalance()
    {

        $account = Account::factory()->create();
        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => $this->faker->safeEmail]
        );

        $company = Company::factory()->create(['account_id' => $account->id]);
        $client = Client::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);

        $credit = Credit::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'balance' => 10,
            'due_date' => now()->addDays(5),
            'number' => 'testing-number-02',
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->assertEquals($client->service()->getCreditBalance(), 0);
        
    }
}
