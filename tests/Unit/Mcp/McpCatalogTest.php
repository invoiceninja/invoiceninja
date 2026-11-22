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
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\RouteCollection;
use ReflectionClass;
use Tests\TestCase;

class McpCatalogTest extends TestCase
{
    public function testNewRoutesAndChangedControllersAreNotAutomaticallyExposed(): void
    {
        $before = app(Catalog::class)->operations();
        \Illuminate\Support\Facades\Route::middleware('token_auth')->get('api/v1/charts/future', 'App\Http\Controllers\ChartController@future');
        \Illuminate\Support\Facades\Route::middleware('token_auth')->get('api/v1/clients/future', 'App\Http\Controllers\ClientController@index');
        $this->assertSame($before, app(Catalog::class)->operations());
        $this->replaceRouteAction('GET', 'api/v1/clients', 'App\Http\Controllers\ClientController@future');
        $this->assertArrayNotHasKey('index', app(Catalog::class)->operations()['clients']);
    }

    private function replaceRouteAction(string $method, string $uri, string $controller_at_method): void
    {
        $collection = app('router')->getRoutes();

        if ($collection instanceof CompiledRouteCollection) {
            $reflection = new ReflectionClass($collection);
            $property = $reflection->getProperty('attributes');
            $property->setAccessible(true);
            $attributes = $property->getValue($collection);
            $replaced = false;

            foreach ($attributes as $name => $definition) {
                if (($definition['uri'] ?? '') !== $uri) {
                    continue;
                }

                $methods = array_values(array_diff($definition['methods'] ?? [], ['HEAD', 'PATCH']));
                if (count($methods) !== 1 || $methods[0] !== $method) {
                    continue;
                }

                $attributes[$name]['action']['uses'] = $controller_at_method;
                $attributes[$name]['action']['controller'] = $controller_at_method;
                $replaced = true;
            }

            if (! $replaced) {
                $this->fail("No {$method} {$uri} route to replace");
            }

            $property->setValue($collection, $attributes);

            $name_cache = $reflection->getProperty('nameCache');
            $name_cache->setAccessible(true);
            $name_cache->setValue($collection, []);

            return;
        }

        if ($collection instanceof RouteCollection) {
            foreach ($collection->getRoutes() as $route) {
                if ($route->uri() !== $uri || ! in_array($method, $route->methods(), true)) {
                    continue;
                }

                $route->setAction(array_merge($route->getAction(), [
                    'uses' => $controller_at_method,
                    'controller' => $controller_at_method,
                ]));

                return;
            }
        }

        $this->fail("No {$method} {$uri} route to replace");
    }

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
