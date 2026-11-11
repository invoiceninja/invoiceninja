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

namespace Tests\Feature;

use App\Models\Project;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Project client_id integrity: prior bug was PUT JSON containing "client_id": null passing through
 * prepareForValidation because isset(null) is false, so fill() cleared client_id. Updates must use
 * array_key_exists when stripping client_id from update payloads.
 *
 * All scenarios use real HTTP payloads only (no factories for Project).
 */
class ProjectClientIdApiTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();
        Model::reguard();
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function assertCreatedProjectResponse(array $payload): array
    {
        $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/projects', $payload);

        $response->assertStatus(200);

        return $response->json();
    }

    public function test_store_project_with_full_api_payload_succeeds(): void
    {
        $body = $this->assertCreatedProjectResponse([
            'client_id' => $this->client->hashed_id,
            'name' => 'Payload Created Project',
            'task_rate' => 75.5,
            'budgeted_hours' => 12,
            'public_notes' => 'notes',
        ]);

        $this->assertNotEmpty($body['data']['id']);
        $this->assertSame('Payload Created Project', $body['data']['name']);
        $this->assertSame(75.5, $body['data']['task_rate']);
        $this->assertSame($this->encodePrimaryKey($this->client->id), $body['data']['client_id']);
    }

    public function test_store_project_without_client_id_returns_422(): void
    {
        $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/projects', [
            'name' => 'Missing Client',
            'task_rate' => 10,
        ]);

        $response->assertStatus(422);
    }

    /**
     * Regression: JSON null must not clear client_id (prepareForValidation must strip by key, not isset).
     */
    public function test_put_payload_with_explicit_null_client_id_does_not_clear_client(): void
    {
        $created = $this->assertCreatedProjectResponse([
            'client_id' => $this->client->hashed_id,
            'name' => 'Before Put Null Client Key',
            'task_rate' => 22,
        ]);

        $hashedProjectId = $created['data']['id'];
        $expectedClientHash = $this->encodePrimaryKey($this->client->id);

        $response = $this->withHeaders($this->apiHeaders())->putJson("/api/v1/projects/{$hashedProjectId}", [
            'client_id' => null,
            'name' => 'After Put Should Keep Client',
            'task_rate' => 33,
            'budgeted_hours' => 0,
        ]);

        $response->assertStatus(200);
        $this->assertSame($expectedClientHash, $response->json('data.client_id'));
        $this->assertSame('After Put Should Keep Client', $response->json('data.name'));

        $this->assertSame(
            $this->client->id,
            Project::query()->find($this->decodePrimaryKey($hashedProjectId))->client_id
        );
    }

    public function test_put_payload_omitting_client_id_preserves_client(): void
    {
        $created = $this->assertCreatedProjectResponse([
            'client_id' => $this->client->hashed_id,
            'name' => 'Omit Client Field',
            'task_rate' => 5,
        ]);

        $hashedProjectId = $created['data']['id'];
        $expectedClientHash = $this->encodePrimaryKey($this->client->id);

        $response = $this->withHeaders($this->apiHeaders())->putJson("/api/v1/projects/{$hashedProjectId}", [
            'name' => 'Renamed Only',
            'task_rate' => 6,
        ]);

        $response->assertStatus(200);
        $this->assertSame($expectedClientHash, $response->json('data.client_id'));

        $this->assertSame(
            $this->client->id,
            Project::query()->find($this->decodePrimaryKey($hashedProjectId))->client_id
        );
    }
}
