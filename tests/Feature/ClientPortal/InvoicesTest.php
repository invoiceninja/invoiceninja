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

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Livewire\InvoicesTable;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\User;
use App\Utils\Number;
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

    public function testDefaultInvoiceViewRemovesStaleGatewayFeeBeforeRenderingPaymentAmount(): void
    {
        $this->withoutVite();

        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        $company = Company::factory()->create(['account_id' => $account->id]);
        $company->settings->language_id = '1';
        $company->save();

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $settings = $client->settings;
        $settings->language_id = '1';
        $settings->payment_flow = 'default';
        $settings->client_portal_allow_under_payment = false;
        $settings->client_portal_allow_over_payment = false;
        $client->settings = $settings;
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'is_primary' => true,
        ]);

        $line_item = InvoiceItemFactory::create();
        $line_item->quantity = 1;
        $line_item->cost = 100;

        $stale_gateway_fee = InvoiceItemFactory::create();
        $stale_gateway_fee->quantity = 1;
        $stale_gateway_fee->cost = 5;
        $stale_gateway_fee->type_id = '3';

        $invoice = InvoiceFactory::create($company->id, $user->id);
        $invoice->client_id = $client->id;
        $invoice->line_items = [$line_item, $stale_gateway_fee];
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        InvoiceInvitation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_contact_id' => $contact->id,
            'invoice_id' => $invoice->id,
        ]);

        $this->assertSame(105.0, (float) $invoice->fresh()->balance);
        $this->assertTrue(collect($invoice->fresh()->line_items)->contains('type_id', '3'));

        $this->actingAs($contact, 'contact');

        $response = $this->get(route('client.invoice.show', ['invoice' => $invoice->hashed_id]));

        $response->assertStatus(200);

        $invoice = $invoice->fresh();

        $this->assertSame(100.0, (float) $invoice->balance);
        $this->assertFalse(collect($invoice->line_items)->contains('type_id', '3'));
        $response->assertSee(
            'name="payable_invoices[0][amount]" value="'.Number::formatValue($invoice->balance, $client->currency()).'"',
            false
        );
    }

    public function testInvoiceTableFilters()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => uniqid('testuser') . '@gmail.com']
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
            ->call('toggleStatus', 'paid')
            ->assertSee($paid->number)
            ->assertDontSee($sent->number)
            ->assertDontSee($unpaid->number);

        $account->delete();

    }

    public function testSelectionResetsOnPagination()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => uniqid('testuser') . '@gmail.com']
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

        Invoice::factory()->count(15)->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $this->actingAs($client->contacts()->first(), 'contact');

        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('select_all', true)
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(10, $c->get('selected')))
            ->call('setPage', 2)
            ->assertSet('selected', [])
            ->assertSet('select_all', false);

        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', ['abc', 'def'])
            ->set('per_page', 15)
            ->assertSet('selected', []);

        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', ['abc'])
            ->call('sortBy', 'number')
            ->assertSet('selected', [])
            ->assertSet('select_all', false);

        $account->delete();
    }

    public function testToggleSelectionAndStatusMethods()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => uniqid('testuser') . '@gmail.com']
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

        $invoices = Invoice::factory()->count(3)->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $first = $invoices->first()->hashed_id;

        $this->actingAs($client->contacts()->first(), 'contact');

        /* toggleSelected adds an id, then a second call removes it */
        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->call('toggleSelected', $first)
            ->assertSet('select_all', false)
            ->tap(fn ($c) => $this->assertContains($first, $c->get('selected')))
            ->call('toggleSelected', $first)
            ->tap(fn ($c) => $this->assertNotContains($first, $c->get('selected')));

        /* toggling a single row clears an active "select all" */
        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->call('toggleSelectAll')
            ->assertSet('select_all', true)
            ->call('toggleSelected', $first)
            ->assertSet('select_all', false)
            ->tap(fn ($c) => $this->assertCount(2, $c->get('selected')))
            ->tap(fn ($c) => $this->assertNotContains($first, $c->get('selected')));

        /* toggleSelectAll selects the visible page, then clears it */
        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->call('toggleSelectAll')
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(3, $c->get('selected')))
            ->call('toggleSelectAll')
            ->assertSet('select_all', false)
            ->assertSet('selected', []);

        /* a partial manual selection followed by select-all then clear-all resets selection */
        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->call('toggleSelected', $first)
            ->assertSet('select_all', false)
            ->tap(fn ($c) => $this->assertContains($first, $c->get('selected')))
            ->call('toggleSelectAll')
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(3, $c->get('selected')))
            ->call('toggleSelectAll')
            ->assertSet('select_all', false)
            ->assertSet('selected', []);

        $invoice_ids = $invoices->pluck('hashed_id')->toArray();

        $component = Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db]);

        foreach ($invoice_ids as $invoice_id) {
            $component->call('toggleSelected', $invoice_id);
        }

        $component
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(3, $c->get('selected')))
            ->call('toggleSelectAll')
            ->assertSet('select_all', false)
            ->assertSet('selected', []);

        /* toggleStatus toggles the value and resets the current selection */
        Livewire::test(InvoicesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', [$first])
            ->call('toggleStatus', 'paid')
            ->assertSet('status', ['paid'])
            ->assertSet('selected', [])
            ->call('toggleStatus', 'paid')
            ->assertSet('status', []);

        $account->delete();
    }
}
