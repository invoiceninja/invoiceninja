<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Mcp;

use App\Mcp\Catalog;
use App\Models\Client;
use App\Models\CompanyToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\Mcp\Fixture;
use Tests\TestCase;

class McpApiTest extends TestCase
{
    use DatabaseTransactions;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mcp.enabled' => true, 'database.default' => config('ninja.db.default')]);
        $this->fixture = (new Fixture())->create();
    }

    private function rpc(string $method, array $params = [], string $role = 'admin', string $tenant = 'alpha')
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->fixture['roles'][$tenant][$role]['token']->token, 'Accept' => 'application/json, text/event-stream'])
            ->postJson('/api/v1/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
    }

    private function callTool(string $tool, array $arguments, string $role = 'admin', string $tenant = 'alpha')
    {
        return $this->rpc('tools/call', ['name' => $tool, 'arguments' => $arguments], $role, $tenant);
    }

    private function clientArguments(string $operation, $client, array $data = []): array
    {
        return ['operation' => $operation, 'parameters' => ['client' => $client->hashed_id], 'data' => (object) $data];
    }

    private function mcpResult($response): array
    {
        $response->assertOk();
        $this->assertNotNull($response->json('result'), $response->getContent());
        return $response->json('result.structuredContent') ?? json_decode($response->json('result.content.0.text'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testProtocolInitializeDiscoveryNotificationsAndUnknownMethod(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'PHPUnit', 'version' => '1']])->assertOk()->assertJsonPath('result.serverInfo.name', 'Invoice Ninja');
        $first = $this->rpc('tools/list', ['per_page' => 2]);
        $first->assertOk();
        $this->assertCount(2, $first->json('result.tools'));
        $second = $this->rpc('tools/list', ['per_page' => 2, 'cursor' => $first->json('result.nextCursor')]);
        $this->assertNotSame($first->json('result.tools.0.name'), $second->json('result.tools.0.name'));
        $tools = $this->rpc('tools/list')->json('result.tools');
        $this->assertCount(count(app(Catalog::class)->operations()), $tools);
        $this->assertContains('tasks', array_column($tools, 'name'));
        $this->rpc('unknown/method')->assertStatus(404)->assertJsonPath('error.code', -32601);
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->fixture['roles']['alpha']['admin']['token']->token])->postJson('/api/v1/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);
    }

    public function testPaymentTermUpdateUsesDaysAndTheSameDerivedLabelAsRest(): void
    {
        $created = $this->mcpResult($this->callTool('payment_terms', ['operation' => 'store', 'data' => ['num_days' => 30]]));
        $id = $created['body']['data']['id'];
        $updated = $this->mcpResult($this->callTool('payment_terms', ['operation' => 'update', 'parameters' => ['payment_term' => $id], 'data' => ['num_days' => 45]]));
        $this->assertSame(45, $updated['body']['data']['num_days']);
        $rest = $this->withHeaders(['X-API-TOKEN' => $this->fixture['roles']['alpha']['admin']['token']->token])->getJson('/api/v1/payment_terms/' . $id);
        $rest->assertOk();
        $this->assertSame($rest->json('data'), $updated['body']['data']);
        $this->assertStringEndsWith('45', $updated['body']['data']['name']);
        $this->assertDatabaseHas('payment_terms', ['company_id' => $this->fixture['companies']['alpha']->id, 'num_days' => 45]);
    }

    public function testNormalizedIncludesAndActionSourcesCannotBypassTransportRestrictions(): void
    {
        $client = $this->fixture['entities']['alpha']['clients'];
        $before = $client->fresh()->getAttributes();
        foreach ([
            ['operation' => 'index', 'query' => ['include' => 'user. companies']],
            ['operation' => 'index', 'query' => ["include\0ignored" => 'user.companies']],
            ['operation' => 'bulk', 'query' => ["action\0ignored" => 'download'], 'data' => ['ids' => [$client->hashed_id]]],
            ['operation' => 'index', 'data' => ['include' => ['contacts,user. company_tokens']]],
            ['operation' => 'bulk', 'query' => ['action' => ' download '], 'data' => ['ids' => [$client->hashed_id], 'action' => 'archive']],
            ['operation' => 'bulk', 'query' => ['action' => 'delete'], 'data' => ['ids' => [$client->hashed_id], 'action' => 'archive']],
            ['operation' => 'bulk', 'data' => ['ids' => [$client->hashed_id], 'action' => "\tBULK_PRINT\n"]],
        ] as $arguments) {
            $reply = $this->callTool('clients', $arguments);
            $reply->assertOk()->assertJsonPath('result.isError', true);
            $this->assertSame($before, $client->fresh()->getAttributes());
        }
        $canonical = $this->mcpResult($this->callTool('clients', ['operation' => 'show', 'parameters' => ['client' => $client->hashed_id], 'query' => ['include' => ' contacts ']]));
        $ordinary = $this->mcpResult($this->callTool('clients', ['operation' => 'show', 'parameters' => ['client' => $client->hashed_id], 'query' => ['include' => 'contacts']]));
        $this->assertSame($ordinary, $canonical);
    }

    public function testProductNotesMutationPersistsAndMatchesRest(): void
    {
        $created = $this->mcpResult($this->callTool('products', ['operation' => 'store', 'data' => ['product_key' => 'MCP product', 'price' => 25, 'notes' => 'Before']]));
        $id = $created['body']['data']['id'];
        $updated = $this->mcpResult($this->callTool('products', ['operation' => 'update', 'parameters' => ['product' => $id], 'data' => ['notes' => 'After']]));
        $this->assertSame('After', $updated['body']['data']['notes']);
        $rest = $this->withHeaders(['X-API-TOKEN' => $this->fixture['roles']['alpha']['admin']['token']->token])->getJson('/api/v1/products/' . $id);
        $rest->assertOk();
        $this->assertSame($rest->json('data'), $updated['body']['data']);
        $this->assertDatabaseHas('products', ['company_id' => $this->fixture['companies']['alpha']->id, 'product_key' => 'MCP product', 'notes' => 'After']);
    }

    public static function domains(): array
    {
        return array_combine(Catalog::DOMAINS, array_map(fn ($domain) => [$domain], Catalog::DOMAINS));
    }

    #[DataProvider('domains')]
    public function testEveryBusinessToolReadsTheSameDataAsRest(string $domain): void
    {
        $spec = app(Catalog::class)->operations()[$domain]['index'];
        $result = $this->mcpResult($this->callTool($domain, ['operation' => 'index', 'query' => ['per_page' => 2]]));
        $rest = $this->withHeaders(['X-API-TOKEN' => $this->fixture['roles']['alpha']['admin']['token']->token])->getJson('/' . $spec['uri'] . '?per_page=2');
        $rest->assertOk();
        $this->assertSame(200, $result['status']);
        $this->assertSame($rest->json('data'), $result['body']['data']);
        $betaId = $this->fixture['entities']['beta'][$domain]->hashed_id;
        $this->assertNotContains($betaId, array_column($result['body']['data'], 'id'));
    }

    public function testInvalidRevokedLockedAndDeletedUsersAreRejectedOnDiscoveryAndCall(): void
    {
        foreach (['tools/list', 'tools/call'] as $method) {
            $params = $method === 'tools/list' ? [] : ['name' => 'clients', 'arguments' => ['operation' => 'index']];
            $this->withHeaders(['Authorization' => 'Bearer invalid'])->postJson('/api/v1/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params])->assertStatus(403);
            $this->rpc($method, $params, 'locked')->assertStatus(403);
        }
        $this->fixture['roles']['alpha']['viewer']['token']->delete();
        $this->rpc('tools/list', [], 'viewer')->assertStatus(403);
        $this->fixture['roles']['alpha']['member']['user']->delete();
        $this->rpc('tools/list', [], 'member')->assertStatus(403);
    }

    public function testOwnerAndAssigneeCanEditWithoutEditPermissionButOtherRecordsAreDenied(): void
    {
        $member = $this->fixture['roles']['alpha']['member']['user'];
        $client = $this->fixture['entities']['alpha']['clients'];
        foreach (['unrelated', 'owner', 'assignee'] as $relationship) {
            $client->user_id = $relationship === 'owner' ? $member->id : $this->fixture['roles']['alpha']['admin']['user']->id;
            $client->assigned_user_id = $relationship === 'assignee' ? $member->id : null;
            $client->save();
            $data = ['name' => 'MCP ' . $relationship];
            $rest = $this->withHeaders(['Authorization' => '', 'X-API-TOKEN' => $this->fixture['roles']['alpha']['member']['token']->token])->putJson('/api/v1/clients/' . $client->hashed_id, $data);
            $result = $this->mcpResult($this->callTool('clients', $this->clientArguments('update', $client, $data), 'member'));
            $this->assertSame($rest->status(), $result['status']);
            $this->assertSame($relationship === 'unrelated' ? 401 : 200, $result['status']);
            if ($relationship !== 'unrelated') {
                $this->assertSame('MCP ' . $relationship, $client->fresh()->name);
            }
        }
    }

    public function testViewPermissionCannotEditAnUnrelatedClientAndPermissionChangesApplyImmediately(): void
    {
        $client = $this->fixture['entities']['alpha']['clients'];
        $this->assertSame(200, $this->mcpResult($this->callTool('clients', $this->clientArguments('show', $client), 'viewer'))['status']);
        $this->assertSame(401, $this->mcpResult($this->callTool('clients', $this->clientArguments('update', $client, ['name' => 'denied']), 'viewer'))['status']);
        $cu = $this->fixture['roles']['alpha']['viewer']['cu'];
        $cu->permissions = '["edit_client"]';
        \App\Models\CompanyUser::query()->where('company_id', $cu->company_id)->where('user_id', $cu->user_id)->update(['permissions' => $cu->permissions]);
        $this->assertSame(200, $this->mcpResult($this->callTool('clients', $this->clientArguments('update', $client, ['name' => 'allowed']), 'viewer'))['status']);
        $this->assertSame('allowed', $client->fresh()->name);
    }

    public function testCompanyIsolationForRouteIdsBodyRelationsAndTokenSwitching(): void
    {
        $beta = $this->fixture['entities']['beta']['clients'];
        $result = $this->mcpResult($this->callTool('clients', $this->clientArguments('show', $beta)));
        $this->assertSame(401, $result['status']);
        $result = $this->mcpResult($this->callTool('invoices', ['operation' => 'store', 'data' => ['client_id' => $beta->hashed_id, 'line_items' => []]]));
        $this->assertSame(422, $result['status']);
        $this->assertSame(200, $this->mcpResult($this->callTool('clients', $this->clientArguments('show', $beta), 'admin', 'beta'))['status']);
        $this->assertSame(401, $this->mcpResult($this->callTool('clients', $this->clientArguments('show', $beta)))['status']);
    }

    public function testDisabledToolsAndOperationsAreDeniedOnDirectCall(): void
    {
        $key = $this->fixture['companies']['alpha']->id . ':' . $this->fixture['roles']['alpha']['admin']['user']->id;
        config(['mcp.disabled_tools' => [$key => ['clients', 'invoices.action']]]);
        $tools = $this->rpc('tools/list')->json('result.tools');
        $this->assertNotContains('clients', array_column($tools, 'name'));
        $this->callTool('clients', ['operation' => 'index'])->assertStatus(400)->assertJsonPath('error.code', -32602);
        $result = $this->callTool('invoices', ['operation' => 'action']);
        $this->assertTrue($result->json('result.isError'));
        $this->rpc('tools/list', [], 'admin', 'beta')->assertOk();
    }

    public function testPathParametersCannotSelectAnotherRestRoute(): void
    {
        $key = $this->fixture['companies']['alpha']->id.':'.$this->fixture['roles']['alpha']['admin']['user']->id;
        config(['mcp.disabled_tools' => [$key => ['clients.create']]]);

        $this->callTool('clients', ['operation' => 'create'])
            ->assertOk()
            ->assertJsonPath('result.isError', true);
        $this->callTool('clients', ['operation' => 'show', 'parameters' => ['client' => 'create']])
            ->assertOk()
            ->assertJsonPath('result.isError', true);
        $this->callTool('documents', ['operation' => 'show', 'parameters' => ['document' => 'create']])
            ->assertOk()
            ->assertJsonPath('result.isError', true);
    }

    public function testValidationMutationsAndPaginationUseOriginalRestBehavior(): void
    {
        $created = $this->mcpResult($this->callTool('clients', ['operation' => 'store', 'data' => ['name' => 'MCP created', 'contacts' => [['email' => 'demo@example.invalid']]]]));
        $this->assertSame(200, $created['status']);
        $this->assertDatabaseHas('clients', ['name' => 'MCP created', 'company_id' => $this->fixture['companies']['alpha']->id]);
        $id = $created['body']['data']['id'];
        $invalid = $this->mcpResult($this->callTool('clients', ['operation' => 'update', 'parameters' => ['client' => $id], 'data' => ['contacts' => [['email' => 'invalid']]]]));
        $this->assertSame(422, $invalid['status']);
        $page = $this->mcpResult($this->callTool('clients', ['operation' => 'index', 'query' => ['per_page' => 1, 'page' => 1]]));
        $this->assertCount(1, $page['body']['data']);
        $this->assertArrayHasKey('pagination', $page['body']['meta']);
    }

    public function testGetMutationChecksEditPolicyAndHasDatabaseEffect(): void
    {
        $invoice = $this->fixture['entities']['alpha']['invoices'];
        $args = ['operation' => 'action', 'parameters' => ['invoice' => $invoice->hashed_id, 'action' => 'mark_sent']];
        $this->assertSame(401, $this->mcpResult($this->callTool('invoices', $args, 'viewer'))['status']);
        $this->assertSame(200, $this->mcpResult($this->callTool('invoices', $args))['status']);
        $this->assertSame(2, (int) $invoice->fresh()->status_id);
    }

    public function testEveryTransportVerbAuthenticatesAndTheServerIsStateless(): void
    {
        $token = $this->fixture['roles']['alpha']['admin']['token']->token;
        foreach (['GET', 'DELETE'] as $method) {
            $this->withHeaders(['Authorization' => 'Bearer invalid']);
            ($method === 'GET' ? $this->getJson('/api/v1/mcp') : $this->deleteJson('/api/v1/mcp'))->assertStatus(403);
            $this->withHeaders(['Authorization' => 'Bearer '.$token]);
            ($method === 'GET' ? $this->getJson('/api/v1/mcp') : $this->deleteJson('/api/v1/mcp'))->assertStatus(405);
        }
        $this->withHeaders(['Authorization' => '', 'X-API-TOKEN' => ''])->postJson('/api/v1/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(403);
        $this->withHeaders(['Mcp-Session-Id' => 'a-client-cannot-pin-an-authenticated-session']);
        $this->rpc('tools/list')->assertOk();
        $this->fixture['roles']['alpha']['admin']['token']->delete();
        $this->callTool('clients', ['operation' => 'index'])->assertStatus(403);
    }

    public function testMalformedProtocolInputAndBoundedResults(): void
    {
        $token = $this->fixture['roles']['alpha']['admin']['token']->token;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Content-Type' => 'application/json'])
            ->call('POST', '/api/v1/mcp', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{')->assertStatus(400)->assertJsonPath('error.code', -32700);
        config(['mcp.max_response_bytes' => 1]);
        $this->callTool('clients', ['operation' => 'index'])->assertOk()->assertJsonPath('result.isError', true);
    }

    public function testGetMutationAllowsTheOwnerAndAssigneeAccordingToTheRestPolicy(): void
    {
        $member = $this->fixture['roles']['alpha']['member']['user'];
        $invoice = $this->fixture['entities']['alpha']['invoices'];
        foreach (['owner', 'assignee'] as $relationship) {
            $invoice->user_id = $relationship === 'owner' ? $member->id : $this->fixture['roles']['alpha']['admin']['user']->id;
            $invoice->assigned_user_id = $relationship === 'assignee' ? $member->id : null;
            $invoice->status_id = 1;
            $invoice->save();
            $args = ['operation' => 'action', 'parameters' => ['invoice' => $invoice->hashed_id, 'action' => 'mark_sent']];
            $this->assertSame(200, $this->mcpResult($this->callTool('invoices', $args, 'member'))['status']);
            $this->assertSame(2, (int)$invoice->fresh()->status_id);
        }
    }
    public function testAccountWideSelectorsAndRelationshipExpansionsAreNotExposed(): void
    {
        $names = array_column($this->rpc('tools/list')->json('result.tools'), 'name');
        $this->assertNotContains('users', $names);
        $this->assertNotContains('statics', $names);
        foreach ([
            ['include' => 'user.companies'],
            ['include' => 'company_users'],
            ['include' => 'activities.user.company_user.company'],
            ['include' => 'activities.user.company_user.token'],
            ['include' => 'account'],
            ['showAccountUsers' => true],
        ] as $query) {
            $this->callTool('clients', ['operation' => 'index', 'query' => $query])->assertOk()->assertJsonPath('result.isError', true);
        }
    }
    public function testSameUserTokensHaveDifferentCompanyMembershipsAndCannotViewForeignOwnedRecords(): void
    {
        $user = $this->fixture['roles']['alpha']['admin']['user'];
        $beta = $this->fixture['companies']['beta'];
        $cu = \App\Factory\CompanyUserFactory::create($user->id, $beta->id, $this->fixture['account']->id);
        $cu->is_admin = false;
        $cu->is_owner = false;
        $cu->is_locked = false;
        $cu->permissions = '["view_all"]';
        $cu->save();
        $token = new CompanyToken();
        $token->forceFill(['user_id' => $user->id, 'company_id' => $beta->id, 'account_id' => $this->fixture['account']->id, 'token' => \Illuminate\Support\Str::random(64), 'name' => 'same user beta fixture', 'is_system' => true])->save();
        $this->fixture['roles']['beta']['same_user'] = ['token' => $token, 'user' => $user, 'cu' => $cu];
        $alphaClient = $this->fixture['entities']['alpha']['clients'];
        $betaClient = $this->fixture['entities']['beta']['clients'];
        $this->assertSame(401, $this->mcpResult($this->callTool('clients', $this->clientArguments('show', $alphaClient), 'same_user', 'beta'))['status']);
        $this->assertSame(200, $this->mcpResult($this->callTool('clients', $this->clientArguments('show', $betaClient), 'same_user', 'beta'))['status']);
        $key = $this->fixture['companies']['alpha']->id.':'.$user->id;
        config(['mcp.disabled_tools' => [$key => ['clients']]]);
        $this->assertNotContains('clients', array_column($this->rpc('tools/list')->json('result.tools'), 'name'));
        $this->assertContains('clients', array_column($this->rpc('tools/list', [], 'same_user', 'beta')->json('result.tools'), 'name'));
        $this->assertSame(200, $this->mcpResult($this->callTool('invoices', ['operation' => 'create']))['status']);
        $this->assertSame(401, $this->mcpResult($this->callTool('invoices', ['operation' => 'create'], 'same_user', 'beta'))['status']);
    }
    public function testOriginConflictingCredentialsDisabledEndpointAndTraversal(): void
    {
        $this->withHeaders(['Origin' => 'https://evil.invalid'])->rpc('tools/list')->assertStatus(403);
        $this->withHeaders(['Origin' => '0'])->rpc('tools/list')->assertStatus(403);
        $this->withHeaders(['Origin' => '', 'X-API-TOKEN' => 'conflict'])->rpc('tools/list')->assertStatus(403);
        $this->flushHeaders();
        $this->callTool('clients', ['operation' => 'show', 'parameters' => ['client' => '../tokens']])->assertOk()->assertJsonPath('result.isError', true);
        $this->callTool('clients', ['operation' => 'store', 'data' => ['_method' => 'DELETE']])->assertOk()->assertJsonPath('result.isError', true);
        config(['mcp.enabled' => false]);
        $this->rpc('tools/list')->assertNotFound();
    }
}
