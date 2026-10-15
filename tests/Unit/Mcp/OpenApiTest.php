<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Mcp;

use App\Mcp\OpenApi;
use App\Mcp\Catalog;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function testEveryReviewedOperationHasAnOpenApiDefinition(): void
    {
        $api = app(OpenApi::class);
        foreach (app(Catalog::class)->operations() as $tool => $operations) {
            foreach ($operations as $operation => $spec) {
                $definition = $api->operation($spec);
                $this->assertNotEmpty($definition['operationId'] ?? null, $tool.'.'.$operation);
                $this->assertNotEmpty($definition['summary'] ?? null, $tool.'.'.$operation);
                $this->assertArrayHasKey('responses', $definition, $tool.'.'.$operation);
            }
        }
    }

    public function testNewRequestDefinitionsReachDiscovery(): void
    {
        $api = app(OpenApi::class);
        $catalog = app(Catalog::class)->operations();
        foreach ([
            ['tax_rates', 'store', 'name', 'string'],
            ['tax_rates', 'store', 'rate', 'number'],
            ['documents', 'update', 'is_public', 'boolean'],
            ['documents', 'bulk', 'ids', 'array'],
            ['designs', 'default', 'design_id', 'string'],
            ['emails', 'entity_history', 'entity_id', 'string'],
            ['invoices', 'payment_schedule', 'parameters', 'object'],
            ['charts', 'cashflow_forecast', 'bucket_type', 'string'],
            ['charts', 'analytics_summary', 'start_date', 'string'],
            ['charts', 'project_burnup', 'include_drafts', 'boolean'],
        ] as [$tool, $operation, $field, $type]) {
            $schema = $api->inputSchema([$operation => $catalog[$tool][$operation]]);
            $this->assertSame($type, $schema['properties']['data']['properties']->{$field}->type);
        }
    }

    public function testReusesExistingRequestSchemaAndMapsPlaceholderNames(): void
    {
        $api = app(OpenApi::class);
        $spec = ['method' => 'POST', 'uri' => 'api/v1/clients', 'parameters' => []];
        $this->assertNotEmpty($api->operation($spec)['summary']);
        $schema = $api->inputSchema(['store' => $spec]);
        $properties = (array)$schema['properties']['data']['properties'];
        $this->assertSame('string', $properties['name']->type);
        $this->assertSame('array', $properties['contacts']->type);
        $show = ['method' => 'GET', 'uri' => 'api/v1/clients/{client}', 'parameters' => ['client']];
        $this->assertNotEmpty($api->operation($show));
        $this->assertObjectHasProperty('client', $api->inputSchema(['show' => $show])['properties']['parameters']['properties']);
        $update = ['method' => 'PUT', 'uri' => 'api/v1/clients/{client}', 'parameters' => ['client']];
        $combined = (array)$api->inputSchema(['store' => $spec, 'update' => $update])['properties']['data']['properties'];
        $this->assertSame('array', $combined['contacts']->type);
    }

    public function testDoesNotResolveExternalReferencesOrRecurseThroughCycles(): void
    {
        foreach (['https://example.invalid/schema', 'file:///etc/passwd', '#/components/schemas/Cycle'] as $ref) {
            $api = new OpenApi([
                'paths' => ['/api/v1/example' => ['post' => ['requestBody' => ['$ref' => $ref]]]],
                'components' => ['schemas' => ['Cycle' => ['$ref' => '#/components/schemas/Cycle']]],
            ]);
            $schema = $api->inputSchema(['store' => ['method' => 'POST', 'uri' => 'api/v1/example', 'parameters' => []]]);
            $this->assertSame([], (array)$schema['properties']['data']['properties']);
        }
    }

    public function testUnknownOperationsRemainPermissiveAndDoNotInventRequestDefinitions(): void
    {
        $api = new OpenApi([]);
        $schema = $api->inputSchema(['store' => ['method' => 'POST', 'uri' => 'api/v1/example', 'parameters' => []]]);
        $this->assertSame(['operation'], $schema['required']);
        $this->assertSame([], (array)$schema['properties']['data']['properties']);
    }
}
