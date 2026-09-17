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
use Illuminate\Support\Facades\Cache;
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
            ->call('toggleStatus', (string) Quote::STATUS_REJECTED)
            ->assertSee($rejected->number)
            ->assertDontSee($sent->number)
            ->assertDontSee($approved->number);

        $account->delete();
    }

    public function testQuoteTableSortsByDateDescendingByDefaultAndDueDateWhenRequested(): void
    {
        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        $company = Company::factory()->create(['account_id' => $account->id]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        $oldest = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'quote-sort-oldest',
            'date' => '2025-01-10',
            'due_date' => '2025-12-31',
            'status_id' => Quote::STATUS_SENT,
        ]);

        $middle = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'quote-sort-middle',
            'date' => '2025-06-15',
            'due_date' => '2025-01-20',
            'status_id' => Quote::STATUS_SENT,
        ]);

        $newest = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'quote-sort-newest',
            'date' => '2025-12-20',
            'due_date' => '2025-06-01',
            'status_id' => Quote::STATUS_SENT,
        ]);

        $this->actingAs($contact, 'contact');

        $component = Livewire::test(QuotesTable::class, [
            'company_id' => $company->id,
            'db' => $company->db,
        ]);

        // Default: quote date descending.
        $this->assertQuoteNumberOrder($component->html(), [
            $newest->number,
            $middle->number,
            $oldest->number,
        ]);

        // The table header must invoke sorting on due_date, not quote date.
        $component->assertSee('wire:click="sortBy(\'due_date\')"', false);

        // First click on a newly selected field uses ascending order.
        $component->call('sortBy', 'due_date');

        $this->assertQuoteNumberOrder($component->html(), [
            $middle->number,
            $newest->number,
            $oldest->number,
        ]);

        // Second click toggles the same field to descending order.
        $component->call('sortBy', 'due_date');

        $this->assertQuoteNumberOrder($component->html(), [
            $oldest->number,
            $newest->number,
            $middle->number,
        ]);

        $account->delete();
    }

    private function assertQuoteNumberOrder(string $html, array $numbers): void
    {
        $positions = array_map(
            fn ($number) => strpos($html, $number),
            $numbers
        );

        $this->assertNotContains(false, $positions);

        foreach (array_keys($positions) as $index) {
            if ($index === 0) {
                continue;
            }

            $this->assertGreaterThan(
                $positions[$index - 1],
                $positions[$index]
            );
        }
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

        $quotes = Quote::factory()->count(3)->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $first = $quotes->first()->hashed_id;

        $this->actingAs($client->contacts()->first(), 'contact');

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->call('toggleSelected', $first)
            ->assertSet('select_all', false)
            ->tap(fn ($c) => $this->assertContains($first, $c->get('selected')))
            ->call('toggleSelected', $first)
            ->tap(fn ($c) => $this->assertNotContains($first, $c->get('selected')));

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->call('toggleSelectAll')
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(3, $c->get('selected')))
            ->call('toggleSelected', $first)
            ->assertSet('select_all', false)
            ->tap(fn ($c) => $this->assertCount(2, $c->get('selected')))
            ->tap(fn ($c) => $this->assertNotContains($first, $c->get('selected')));

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->call('toggleSelectAll')
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(3, $c->get('selected')))
            ->call('toggleSelectAll')
            ->assertSet('select_all', false)
            ->assertSet('selected', []);

        Livewire::test(QuotesTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', [$first])
            ->call('toggleStatus', (string) Quote::STATUS_REJECTED)
            ->assertSet('status', [(string) Quote::STATUS_REJECTED])
            ->assertSet('selected', [])
            ->call('toggleStatus', (string) Quote::STATUS_REJECTED)
            ->assertSet('status', []);

        $account->delete();
    }

    public function testQuoteApprovalRouteDoesNotAcceptGetRequests(): void
    {
        $route = app('router')->getRoutes()->getByName('client.quotes.bulk');

        $this->assertSame(['POST'], $route->methods());
    }

    public function testQuoteApprovalContinuationUsesPostAndConsumesTheCachedRequest(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);
        $company = Company::factory()->create(['account_id' => $account->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);
        $settings = $client->settings;
        $settings->auto_convert_quote = false;
        $client->settings = $settings;
        $client->save();
        $contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);
        $quote = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'due_date' => now()->addMonth(),
            'status_id' => Quote::STATUS_SENT,
        ]);
        $request_hash = str_repeat('a', 64);

        Cache::put($request_hash, [
            'client_contact_id' => $contact->id,
            'request' => [
                'action' => 'approve',
                'process' => 'true',
                'quotes' => [$quote->hashed_id],
            ],
        ], 60);

        $this->actingAs($contact, 'contact');

        $this->get(route('client.quotes.bulk', [
            'action' => 'approve',
            'process' => 'true',
            'quotes' => [$quote->hashed_id],
        ]))->assertNotFound();

        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);

        $this->get(route('client.quotes.approval.continue', $request_hash))
            ->assertOk()
            ->assertSee('method="post"', false)
            ->assertSee($request_hash);

        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);
        $this->assertTrue(Cache::has($request_hash));

        $response = $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'request_hash' => $request_hash,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame(Quote::STATUS_APPROVED, $quote->fresh()->status_id);
        $this->assertFalse(Cache::has($request_hash));

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'request_hash' => $request_hash,
        ])
            ->assertNotFound();

        $account->delete();
    }

    public function testQuoteApprovalContinuationIsBoundToTheContact(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);
        $company = Company::factory()->create(['account_id' => $account->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);
        [$contact, $other_contact] = ClientContact::factory()->count(2)->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ])->all();
        $request_hash = str_repeat('b', 64);

        Cache::put($request_hash, [
            'client_contact_id' => $contact->id,
            'request' => [
                'action' => 'approve',
                'process' => 'true',
                'quotes' => [],
            ],
        ], 60);

        $this->actingAs($other_contact, 'contact')
            ->get(route('client.quotes.approval.continue', $request_hash))
            ->assertNotFound();

        $this->assertTrue(Cache::has($request_hash));

        $account->delete();
    }
}
