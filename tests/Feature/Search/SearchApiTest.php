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

namespace Tests\Feature\Search;

use App\Http\Controllers\SearchController;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\MockAccountData;
use Tests\TestCase;

#[CoversClass(SearchController::class)]
class SearchApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        config(['scout.driver' => null]);
    }

    public function testActivityEntity()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/search', []);

        $response->assertStatus(200);
    }

    public function testSearchFallbackDoesNotNPlusOneQueries(): void
    {
        $this->seedSearchableClients(8);

        $baseline = $this->measureSearchQueryCount();
        $baselineCount = $baseline[0];
        $baselineClients = $baseline[1];
        $baselineInvoices = $baseline[2];

        $this->seedSearchableClients(8);

        $second = $this->measureSearchQueryCount();
        $secondCount = $second[0];
        $secondClients = $second[1];
        $secondInvoices = $second[2];
        $queries = $second[3];

        $this->assertGreaterThan($baselineClients, $secondClients);
        $this->assertGreaterThan($baselineInvoices, $secondInvoices);

        $queryDescriptions = array_map(fn (array $query): string => $query['query'], $queries);

        $this->assertLessThanOrEqual(
            $baselineCount + 2,
            $secondCount,
            'N+1 on POST /api/v1/search: queries grew from '
            . "{$baselineCount} ({$baselineClients} clients, {$baselineInvoices} invoices) "
            . "to {$secondCount} ({$secondClients} clients, {$secondInvoices} invoices).\n"
            . "Queries:\n" . implode("\n", $queryDescriptions)
        );
    }

    public function testSearchFallbackResolvesNamelessClientsWithoutReloadingClient(): void
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => '',
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'is_primary' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/search', []);

        $response->assertStatus(200);

        $client_names = collect($response->json('clients'))->pluck('name');
        $contact_names = collect($response->json('client_contacts'))->pluck('name');

        $this->assertTrue($client_names->contains('Ada Lovelace'));
        $this->assertTrue($contact_names->contains(fn (string $name): bool => str_contains($name, 'Ada Lovelace')));
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3?: array<int, array<string, mixed>>}
     */
    private function measureSearchQueryCount(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/search', []);

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return [
            count($queries),
            count($response->json('clients')),
            count($response->json('invoices')),
            $queries,
        ];
    }

    private function seedSearchableClients(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $client = Client::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => $i % 2 === 0 ? "Search Client {$i}" : '',
            ]);

            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $client->id,
                'first_name' => $i % 2 === 0 ? 'Pat' : '',
                'last_name' => $i % 2 === 0 ? 'Contact' : '',
                'email' => "search-{$i}@example.com",
                'is_primary' => true,
            ]);

            Invoice::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $client->id,
                'number' => 'SEARCH-' . uniqid((string) $i, true),
            ]);
        }
    }
}
