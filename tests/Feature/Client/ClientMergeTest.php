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

namespace Tests\Feature\Client;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Http\Livewire\CreditsTable;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ClientMergeTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    private $user;

    private $company;

    private $account;

    public $client;

    private $primary_contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->buildCache(true);
    }

    public function testSearchingForContacts()
    {
        $account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $this->company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
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

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'search@gmail.com',
        ]);

        $this->assertEquals(4, $this->client->contacts->count());
        $this->assertTrue($this->client->contacts->contains(function ($contact) {
            return $contact->email == 'search@gmail.com';
        }));

        $this->assertFalse($this->client->contacts->contains(function ($contact) {
            return $contact->email == 'false@gmail.com';
        }));
    }

    public function testMergeClients()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $primary_contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'is_primary' => 1,
        ]);

        ClientContact::factory()->count(2)->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'email' => 'search@gmail.com',
        ]);
        //4contacts

        $mergable_client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $primary_contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $mergable_client->id,
            'company_id' => $company->id,
            'is_primary' => 1,
        ]);

        ClientContact::factory()->count(2)->create([
            'user_id' => $user->id,
            'client_id' => $mergable_client->id,
            'company_id' => $company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $mergable_client->id,
            'company_id' => $company->id,
            'email' => 'search@gmail.com',
        ]);
        //4 contacts

        $this->assertEquals(4, $client->contacts->count());
        $this->assertEquals(4, $mergable_client->contacts->count());

        $client = $client->service()->merge($mergable_client)->save();

        // nlog($client->contacts->fresh()->toArray());
        // $this->assertEquals(7, $client->fresh()->contacts->count());
    }
}
