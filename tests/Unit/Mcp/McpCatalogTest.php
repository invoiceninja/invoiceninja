<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Mcp;

use App\Mcp\Catalog;
use Tests\TestCase;

class McpCatalogTest extends TestCase
{
    public function testCatalogIsClosedAndEveryOperationUsesAnAuthenticatedRestRoute(): void
    {
        $catalog = app(Catalog::class)->operations();
        $this->assertArrayHasKey('clients', $catalog);
        $this->assertArrayHasKey('tasks', $catalog);
        $this->assertArrayHasKey('purchase_orders', $catalog);
        $this->assertArrayNotHasKey('tokens', $catalog);
        $this->assertArrayNotHasKey('accounts', $catalog);
        $seen = [];
        foreach ($catalog as $domain => $operations) {
            foreach ($operations as $operation => $spec) {
                $key = $spec['method'] . ' ' . $spec['uri'];
                $this->assertNotContains($key, $seen, 'Duplicate catalog operation ' . $domain . '.' . $operation);
                $seen[] = $key;
                $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/' . preg_replace('/\{[^}]+\}/', 'mcp-demo', $spec['uri']), $spec['method']));
                $this->assertContains('token_auth', $route->gatherMiddleware());
                $this->assertSame($spec['controller'], $route->getActionName());
            }
        }
    }

    public function testGetActionsAreNotDescribedAsReadOnly(): void
    {
        $action = app(Catalog::class)->operations()['invoices']['action'];
        $this->assertSame('GET', $action['method']);
        $this->assertFalse($action['read_only']);
    }

    public function testEveryApiVerbHasEitherAToolOrAnExclusionReason(): void
    {
        $rows = array_column(app(Catalog::class)->inventory(), null, 'api');
        foreach ($rows as $row) {
            $this->assertTrue($row['tool'] !== null || is_string($row['exclusion']) && strlen($row['exclusion']) > 30, $row['api']);
            if (str_starts_with($row['api'], 'PATCH ')) {
                $put = str_replace('PATCH ', 'PUT ', $row['api']);
                $this->assertArrayHasKey($put, $rows);
                $this->assertSame($rows[$put]['tool'], $row['tool']);
            }
        }
    }
}
