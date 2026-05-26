<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\ClientPortal;

use App\Livewire\QuotesTable;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Quote;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class QuotesTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testQuoteTableFilters()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => $this->faker->safeEmail()]
        );

        $company = Company::factory()->create(['account_id' => $account->id]);
        $company->settings->language_id = '1';
        $company->save();

        $client = Client::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        $settings = $client->settings;
        $settings->language_id = '1';
        $client->settings = $settings;
        $client->save();

        ClientContact::factory()->count(2)->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        $sent = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'quote-testing-number-01',
            'due_date' => now()->addMonth(),
            'status_id' => Quote::STATUS_SENT,
        ]);

        $approved = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'quote-testing-number-02',
            'status_id' => Quote::STATUS_APPROVED,
        ]);

        $rejected = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'quote-testing-number-03',
            'status_id' => Quote::STATUS_REJECTED,
        ]);

        $sent->load('client');
        $approved->load('client');
        $rejected->load('client');

        $this->actingAs($client->contacts()->first(), 'contact');

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->assertSee($sent->number)
            ->assertSee($approved->number)
            ->assertSee($rejected->number);

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('status', ['5'])
            ->assertSee($rejected->number)
            ->assertDontSee($approved->number);

        $account->delete();
    }

    public function testSelectionResetsOnPagination()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => $this->faker->safeEmail()]
        );

        $company = Company::factory()->create(['account_id' => $account->id]);
        $company->settings->language_id = '1';
        $company->save();

        $client = Client::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        $settings = $client->settings;
        $settings->language_id = '1';
        $client->settings = $settings;
        $client->save();

        ClientContact::factory()->count(2)->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        Quote::factory()->count(15)->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $this->actingAs($client->contacts()->first(), 'contact');

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('select_all', true)
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(10, $c->get('selected')))
            ->call('setPage', 2)
            ->assertSet('selected', [])
            ->assertSet('select_all', false);

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', ['abc', 'def'])
            ->set('per_page', 15)
            ->assertSet('selected', []);

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', ['abc'])
            ->call('sortBy', 'number')
            ->assertSet('selected', [])
            ->assertSet('select_all', false);

        $account->delete();
    }
}
