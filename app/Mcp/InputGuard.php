<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

use InvalidArgumentException;

/** Additional transport restrictions; REST authorization and validation still apply. */
final class InputGuard
{
    private const BULK_ACTIONS = [
        'bank_transactions' => ['convert_matched', 'unlink'],
        'clients' => ['assign_group', 'bulk_update', 'clone'],
        'credits' => ['email', 'mark_paid', 'clone_to_credit', 'mark_sent', 'send_email'],
        'designs' => ['clone'],
        'expenses' => ['bulk_update', 'bulk_categorize'],
        'invoices' => ['email', 'send_email', 'mark_paid', 'mark_sent', 'cancel', 'auto_bill', 'clone_to_invoice', 'convert_to_purchase_order', 'set_payment_link'],
        'payments' => ['email', 'email_receipt'],
        'products' => ['set_tax_id'],
        'projects' => ['invoice'],
        'purchase_orders' => ['email', 'mark_sent', 'send_email', 'add_to_inventory', 'expense', 'cancel'],
        'quotes' => ['convert', 'convert_to_invoice', 'convert_to_project', 'email', 'clone_to_quote', 'convert_to_purchase_order', 'approve', 'send_email', 'mark_sent', 'cancel'],
        'recurring_expenses' => ['start', 'stop'],
        'recurring_invoices' => ['increase_prices', 'update_prices', 'start', 'stop', 'send_now', 'set_payment_link', 'bulk_update'],
        'subscriptions' => ['assign_invoice'],
        'tasks' => ['bulk_update', 'start', 'stop'],
    ];

    private const ENTITY_ACTIONS = [
        'credits' => ['mark_paid', 'clone_to_credit', 'mark_sent', 'email', 'send_email'],
        'invoices' => ['auto_bill', 'clone_to_invoice', 'clone_to_quote', 'convert_to_purchase_order', 'mark_paid', 'mark_sent', 'cancel'],
        'purchase_orders' => ['mark_sent', 'email', 'send_email', 'add_to_inventory', 'expense', 'cancel'],
        'quotes' => ['convert_to_project', 'convert', 'convert_to_invoice', 'clone_to_invoice', 'clone_to_quote', 'convert_to_purchase_order', 'approve', 'cancel', 'email', 'send_email', 'mark_sent'],
    ];

    public function normalize(array $spec, array $parameters, array $query, array $data): array
    {
        // PHP query parsing rewrites dots/spaces/brackets and truncates NUL bytes in keys.
        // Accept structured arrays as values, never an alternate spelling of a selector.
        foreach (array_keys($query) as $key) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z_][a-zA-Z_0-9]*$/D', $key)) {
                throw new InvalidArgumentException('Query keys must be plain REST parameter names; use JSON values for arrays.');
            }
        }
        foreach ([&$query, &$data] as &$input) {
            foreach (['_method', 'X-API-TOKEN', 'X-API-SECRET', 'Authorization', 'showAccountUsers', 'include_static'] as $reserved) {
                if (array_key_exists($reserved, $input)) {
                    throw new InvalidArgumentException('Credentials, method overrides and account-wide selectors cannot be tool arguments.');
                }
            }
            if (array_key_exists('include', $input)) {
                $input['include'] = $this->includes($input['include']);
            }
        }
        unset($input);

        $actions = [];
        $domain = explode('/', $spec['uri'])[2];
        $allowlist = in_array('action', $spec['parameters'], true) ? self::ENTITY_ACTIONS : self::BULK_ACTIONS;
        $allowed = array_merge(['archive', 'restore', 'delete'], $allowlist[$domain] ?? []);
        foreach ([&$parameters, &$query, &$data] as &$input) {
            if (!array_key_exists('action', $input)) {
                continue;
            }
            if (!is_string($input['action'])) {
                throw new InvalidArgumentException('Actions must be strings.');
            }
            $action = strtolower(preg_replace('/\s+/u', '', $input['action']) ?? '');
            if (!in_array($action, $allowed, true)) {
                throw new InvalidArgumentException('Action is outside the reviewed JSON MCP catalog. Use REST for files and unsupported actions.');
            }
            $input['action'] = $action;
            $actions[] = $action;
        }
        unset($input);
        if (count(array_unique($actions)) > 1) {
            throw new InvalidArgumentException('Conflicting action selectors are not allowed.');
        }
        if ($actions && !in_array('action', $spec['parameters'], true) && !str_ends_with($spec['uri'], '/bulk')) {
            throw new InvalidArgumentException('This operation does not accept an action selector.');
        }
        return [$parameters, $query, $data];
    }

    private function includes(mixed $value): string
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Includes must be relationship names.');
            }
            foreach (explode(',', $value) as $include) {
                $include = preg_replace('/\s+/u', '', $include);
                if ($include === '') {
                    continue;
                }
                // Do not forward syntax interpreted differently by the controller and Fractal.
                if ($include === null || !preg_match('/^[a-zA-Z_][a-zA-Z_0-9]*(\.[a-zA-Z_][a-zA-Z_0-9]*)*$/D', $include)) {
                    throw new InvalidArgumentException('Includes must be dot-separated relationship names without modifiers.');
                }
                foreach (explode('.', $include) as $segment) {
                    $name = strtolower(str_replace('_', '', $segment));
                    if (in_array($name, ['account', 'accounts', 'companies', 'companyusers', 'tokens', 'companytokens', 'companygateways'], true)) {
                        throw new InvalidArgumentException('Account and credential includes are outside this company-bound catalog.');
                    }
                }
                $normalized[] = $include;
            }
        }
        return implode(',', array_unique($normalized));
    }
}
