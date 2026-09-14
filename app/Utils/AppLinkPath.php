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

namespace App\Utils;

/**
 * Translates the route inside a shared record link into the web client's —
 * invoiceninja/flutter#144.
 *
 * A link carries the *app's* route, because that is the shape the OS verifies
 * and the apps parse; the web client names a few screens differently and mounts
 * most records only under `:id/edit`. Translating here rather than in the apps
 * keeps the link itself one shape on every platform. Dependency-free so the two
 * rules that fail silently can be unit tested.
 */
class AppLinkPath
{
    /**
     * The web client's path for an app route, without a leading slash.
     *
     * Roots are matched explicitly, longest first, because it is the only way to
     * tell a record from a list: `settings/bank_accounts` and `clients/<id>` both
     * have two segments, so counting them would suffix the list with `/edit`. An
     * unrecognised root passes through untouched — a section beats a 404.
     */
    public static function forWebClient(string $path): string
    {
        $path = trim(preg_replace('#/+#', '/', $path), '/');

        if ($path === '') {
            return '';
        }

        $roots = self::routeMap();
        uksort($roots, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($roots as $appRoot => $webRoot) {
            if ($path !== $appRoot && ! str_starts_with($path, $appRoot.'/')) {
                continue;
            }

            $rest = trim(substr($path, strlen($appRoot)), '/');

            if ($rest === '') {
                return $webRoot;
            }

            if (str_ends_with($rest, '/edit') || $rest === 'edit') {
                return $webRoot.'/'.$rest;
            }

            // A bare `:id` matches the web client's shell and renders an empty
            // page for most entities, which is why Inviteable::getReactLink
            // links to /edit as well.
            return $webRoot.'/'.$rest.'/edit';
        }

        return $path;
    }

    /**
     * Every route root a shared link can carry, mapped to the web client's name
     * for the same screen — identical on all but four. Adding an entity to the
     * apps means adding its root here, or its links lose the `/edit` the web
     * client needs; nothing on this side can check that automatically.
     */
    public static function routeMap(): array
    {
        $identity = [
            'clients',
            'credits',
            'expenses',
            'invoices',
            'payments',
            'products',
            'projects',
            'purchase_orders',
            'quotes',
            'recurring_expenses',
            'recurring_invoices',
            'tasks',
            'transactions',
            'vendors',
            'settings/bank_accounts',
            'settings/bank_accounts/transaction_rules',
            'settings/expense_categories',
            'settings/group_settings',
            'settings/invoice_design/custom_designs',
            'settings/payment_terms',
            'settings/schedules',
            'settings/tags',
            'settings/task_statuses',
            'settings/tax_rates',
        ];

        return array_merge(
            array_combine($identity, $identity),
            [
                // The same entity under a different name on each side.
                'settings/company_gateways' => 'settings/gateways',
                'settings/payment_links' => 'settings/subscriptions',
                // The apps file these under Account Management; the web client
                // does not.
                'settings/account_management/integrations/api_tokens' => 'settings/integrations/api_tokens',
                'settings/account_management/integrations/api_webhooks' => 'settings/integrations/api_webhooks',
            ]
        );
    }
}
