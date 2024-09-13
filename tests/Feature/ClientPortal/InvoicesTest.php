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

namespace Tests\Feature\ClientPortal;

use App\Livewire\InvoicesTable;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicesTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testInvoiceTableFilters()
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

        $sent = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'testing-number-02',
            'due_date' => now()->addMonth(),
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $paid = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'testing-number-03',
            'status_id' => Invoice::STATUS_PAID,
        ]);

        $unpaid = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'testing-number-04',
            'due_date' => '',
            'status_id' => Invoice::STATUS_UNPAID,
        ]);

        $sent->load('client');
        $paid->load('client');
        $unpaid->load('client');

        $this->actingAs($client->contacts()->first(), 'contact');

        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->assertSee($sent->number)
            ->assertSee($paid->number)
            ->assertSee($unpaid->number);

        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('status', ['paid'])
            ->assertSee($paid->number)
            ->assertDontSee($unpaid->number);

        $account->delete();

    }
}
