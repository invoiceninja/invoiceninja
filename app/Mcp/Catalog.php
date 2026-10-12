<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

use Illuminate\Support\Facades\Route;

/** A closed catalog: clients cannot supply a URL, controller, method, or headers. */
class Catalog
{
    public const DOMAINS = [
        'clients', 'invoices', 'quotes', 'credits', 'payments', 'expenses',
        'recurring_expenses', 'recurring_invoices', 'recurring_quotes',
        'products', 'vendors', 'projects', 'tasks', 'task_statuses',
        'expense_categories', 'tax_rates', 'payment_terms', 'group_settings',
        'locations', 'bank_transactions', 'bank_transaction_rules', 'documents',
        'designs', 'subscriptions', 'webhooks', 'purchase_orders', 'tags',
    ];

    public function operations(): array
    {
        $operations = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            $parts = explode('/', substr($uri, 7));
            $domain = $parts[0];
            $action = $route->getActionMethod();
            $methods = array_values(array_diff($route->methods(), ['HEAD', 'PATCH']));
            if (count($methods) !== 1 || !in_array('token_auth', $route->gatherMiddleware(), true)) {
                continue;
            }
            $method = $methods[0];
            if (in_array('password_protected', $route->gatherMiddleware(), true) || $domain === 'task_statuses' && $action === 'edit' || $domain === 'tasks' && $action === 'sort') {
                continue;
            }
            $name = null;
            if (in_array($domain, self::DOMAINS, true)) {
                $crud = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
                if (in_array($action, $crud, true)) {
                    if ($domain === 'documents' && in_array($action, ['create', 'store'], true)) {
                        continue; // Empty REST controller methods, not implemented operations.
                    }
                    $name = $action;
                } elseif (in_array($action, ['bulk', 'action', 'paymentSchedule', 'deletePaymentSchedule', 'update_reminders', 'sort', 'documents', 'showSettings', 'vatStatus', 'default', 'match', 'refund'], true)) {
                    $name = \Illuminate\Support\Str::snake($action);
                }
            } elseif ($domain === 'emails' && in_array($action, ['send', 'clientHistory', 'entityHistory'], true)) {
                $name = \Illuminate\Support\Str::snake($action);
            } elseif ($domain === 'charts') {
                $name = \Illuminate\Support\Str::snake($action);
            } elseif ($domain === 'activities' && in_array($action, ['index', 'entityActivity', 'note'], true)) {
                $name = \Illuminate\Support\Str::snake($action);
            } elseif (in_array($domain, ['company_ledger', 'search'], true) && (in_array($action, ['index', '__invoke'], true) || in_array($domain, ['search', 'statics'], true))) {
                $name = 'index';
            }
            if ($name === null) {
                continue;
            }
            preg_match_all('/\{([^}?]+)\??\}/', $uri, $parameters);
            $operations[$domain][$name] = [
                'method' => $method, 'uri' => $uri, 'parameters' => $parameters[1],
                'controller' => $route->getActionName(),
                // GET action endpoints mutate. Only explicitly reviewed reads get this hint.
                'read_only' => in_array($name, ['index', 'show', 'edit', 'vat_status'], true)
                    || $domain === 'charts' || $domain === 'search',
            ];
        }
        ksort($operations);
        foreach ($operations as &$items) {
            ksort($items);
        }
        return $operations;
    }

    public function enabled(string $tool, ?string $operation = null): bool
    {
        if (!config('mcp.enabled') || !auth()->user()) {
            return false;
        }
        $key = auth()->user()->companyId().':'.auth()->id();
        $disabled = config('mcp.disabled_tools.'.$key, []);
        return !in_array($tool, $disabled, true)
            && ($operation === null || !in_array($tool.'.'.$operation, $disabled, true));
    }

    public function available(string $tool): array
    {
        if (!$this->enabled($tool)) {
            return [];
        }
        return array_filter($this->operations()[$tool] ?? [], fn ($spec, $name) => $this->enabled($tool, $name), ARRAY_FILTER_USE_BOTH);
    }

    public function inventory(): array
    {
        $mapped = [];
        foreach ($this->operations() as $tool => $operations) {
            foreach ($operations as $operation => $spec) {
                $mapped[$spec['method'].' '.$spec['uri']] = $tool.'.'.$operation;
            }
        }
        $inventory = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/') && !in_array('api', $route->getAction('middleware') ?? [], true)) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.$route->uri();
                $alias = $method === 'PATCH' ? 'PUT '.$route->uri() : $key;
                $tool = $mapped[$alias] ?? null;
                $inventory[] = [
                    'api' => $key, 'controller' => $route->getActionName(), 'tool' => $tool,
                    'exclusion' => $tool ? null : $this->exclusion($route),
                    'test' => $tool ? 'tools/mcp/full-e2e.mjs; tests/Feature/Mcp/McpApiTest.php (authentication/policies)' : null,
                    'result' => $tool ? 'pending' : 'excluded',
                ];
            }
        }
        return $inventory;
    }

    private function exclusion($route): string
    {
        $uri = $route->uri();
        $action = $route->getActionMethod();
        if ($uri === 'api/v1/statics') {
            return 'Instance-wide statics also carry licensing material; not an isolated tenant business response.';
        }
        if ($uri === 'api/v1/tasks/sort') {
            return 'Task board reordering requires an authorization review before MCP exposure; not included in the business catalog.';
        }
        if ($uri === 'api/v1/task_statuses/{task_status}/edit') {
            return 'REST edit form has a controller parameter binding mismatch and returns an authorization denial even for the fixture administrator. Use show/update instead.';
        }
        if (!in_array('token_auth', $route->gatherMiddleware(), true)) {
            return 'Authentication/bootstrap, OAuth callback, or external webhook; not a CompanyToken business operation.';
        }
        if (in_array('password_protected', $route->gatherMiddleware(), true)) {
            return 'Interactive password confirmation is required by REST; MCP cannot replace that confirmation.';
        }
        if (str_contains($uri, 'upload') || str_contains($uri, 'download') || str_contains($uri, 'preview') || str_contains($uri, 'reports/') || str_contains($uri, 'templates') || str_contains($uri, 'export') || str_contains($uri, 'import')) {
            return 'Multipart, streamed file, HTML preview, or file import/export workflow; this transport exposes JSON business operations only.';
        }
        if (str_contains($uri, 'documents/create') || $action === 'store' && str_contains($uri, 'documents')) {
            return 'REST controller method is an unimplemented placeholder.';
        }
        $domain = explode('/', substr($uri, 7))[0];
        if (in_array($domain, ['ping', 'health_check', 'last_error', 'system_logs', 'feedback', 'check_subdomain', 'check_license', 'claim_license'], true)) {
            return 'Instance diagnostics, licensing, feedback or deployment provisioning; not a tenant business operation.';
        }
        if (in_array($domain, ['accounts', 'companies', 'company_gateways', 'company_users', 'users', 'tokens', 'client_gateway_tokens', 'settings', 'password_timeout', 'logout', 'one_time_token', 'refresh', 'refresh_react'], true)) {
            return 'Account, credential or company administration can change identity, secrets or company selection; outside this CompanyToken-bound business catalog.';
        }
        if (in_array($domain, ['bank_integrations', 'calendar_connection', 'connected_account', 'quickbooks', 'einvoice', 'brevo', 'smtp', 'mailer', 'stripe', 'yodlee', 'nordigen', 'verify', 'subscriptions'], true)) {
            return 'External provider enrollment, verification, synchronization or checkout flow; requires a provider-specific authenticated/interactive setup not represented by this JSON business transport.';
        }
        return 'External delivery, provider lookup, interactive confirmation or a non-JSON business workflow is not implemented by this catalog; its REST endpoint remains available. See MCP.md.';
    }
}
