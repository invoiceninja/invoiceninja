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

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\ClientContact;
use App\Repositories\ClientRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

class PurgeClientRequestTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testHostedFranceReportingBlocksPurge(): void
    {
        config(['ninja.environment' => 'hosted']);
        $this->enableFranceReporting();
        $client = $this->createPurgeableClient();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson("/api/v1/clients/{$client->hashed_id}/purge");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('client')
            ->assertJsonPath(
                'errors.client.0',
                'The client cannot be purged while France reporting is enabled.',
            );
        $this->assertNotNull(Client::withTrashed()->find($client->id));
    }

    public function testHostedWithoutFranceReportingAllowsPurge(): void
    {
        config(['ninja.environment' => 'hosted']);
        $client = $this->createPurgeableClient();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson("/api/v1/clients/{$client->hashed_id}/purge");

        $response->assertOk();
        $this->assertNull(Client::withTrashed()->find($client->id));
    }

    public function testSelfHostWithFranceReportingAllowsPurge(): void
    {
        config(['ninja.environment' => 'selfhost']);
        $this->enableFranceReporting();
        $client = $this->createPurgeableClient();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson("/api/v1/clients/{$client->hashed_id}/purge");

        $response->assertOk();
        $this->assertNull(Client::withTrashed()->find($client->id));
    }

    public function testRepositoryPurgeNoLongerInspectsFranceReportingHistory(): void
    {
        $client = $this->createPurgeableClient();

        app(ClientRepository::class)->purge($client);

        $this->assertNull(Client::withTrashed()->find($client->id));
    }

    /** @return array<string, string> */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => config('ninja.testvars.password'),
        ];
    }

    private function enableFranceReporting(): void
    {
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = true;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
    }

    private function createPurgeableClient(): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => true,
        ]);

        return $client;
    }
}
