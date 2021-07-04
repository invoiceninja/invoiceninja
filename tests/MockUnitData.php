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

namespace Tests;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
/**
 * Class MockUnitData.
 */
trait MockUnitData
{
    public $account;

    public $company;

    public $user;

    public $client;

    public $faker;

    public $primary_contact;

    public function makeTestData()
    {

        $this->faker = \Faker\Factory::create();

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id, 
            'email' => $this->faker->safeEmail
        ]);

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id
        ]);

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id, 
            'company_id' => $this->company->id
        ]);
            
        $this->primary_contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        ClientContact::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

    }
}