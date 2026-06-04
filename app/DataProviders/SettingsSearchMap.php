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

namespace App\DataProviders;

/**
 * Static catalogue of searchable settings destinations.
 *
 * Each entry describes a navigable settings route together with the metadata
 * required to render and gate it inside the global search palette:
 *
 *  - path:       the front-end route to navigate to.
 *  - section:    translation key used as the bold heading (the parent group);
 *                null resolves to the generic "settings" heading.
 *  - label:      translation key used as the result name (the leaf).
 *  - keywords:   space separated, untranslated synonyms that widen matching
 *                (e.g. "smtp mailgun postmark" for the email settings page).
 *  - permission: gate required to surface the entry. SettingsSearchMap::ADMIN
 *                requires an admin/owner; null exposes the entry to every user.
 *  - scope:      deployment scope, one of SettingsSearchMap::SCOPE_*.
 *
 * @phpstan-type SettingsSearchEntry array{path: string, section: string|null, label: string, keywords: string, permission: string|null, scope: string}
 */
class SettingsSearchMap
{
    public const ADMIN = 'admin';

    public const SCOPE_ALL = 'all';

    public const SCOPE_HOSTED = 'hosted';

    public const SCOPE_SELFHOST = 'selfhost';

    /**
     * The full settings catalogue.
     *
     * @return array<int, SettingsSearchEntry>
     */
    public static function all(): array
    {
        return [
            // User details — available to every authenticated user (own profile).
            ['path' => '/settings/user_details', 'section' => null, 'label' => 'user_details', 'keywords' => 'profile account me', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/password', 'section' => 'user_details', 'label' => 'password', 'keywords' => 'security login credentials change password', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/connect', 'section' => 'user_details', 'label' => 'connect', 'keywords' => 'oauth google microsoft apple social login link', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/accent_color', 'section' => 'user_details', 'label' => 'accent_color', 'keywords' => 'theme colour color appearance', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/notifications', 'section' => 'user_details', 'label' => 'notifications', 'keywords' => 'alerts emails notify', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/enable_two_factor', 'section' => 'user_details', 'label' => 'enable_two_factor', 'keywords' => '2fa mfa totp authenticator security otp', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/custom_fields', 'section' => 'user_details', 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'preferences', 'keywords' => 'options settings', 'permission' => null, 'scope' => self::SCOPE_ALL],

            // Company details.
            ['path' => '/settings/company_details', 'section' => null, 'label' => 'company_details', 'keywords' => 'business organisation organization', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/details', 'section' => 'company_details', 'label' => 'details', 'keywords' => 'name id number website', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/address', 'section' => 'company_details', 'label' => 'address', 'keywords' => 'street city postcode state country location', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/logo', 'section' => 'company_details', 'label' => 'logo', 'keywords' => 'branding image picture', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/defaults', 'section' => 'company_details', 'label' => 'defaults', 'keywords' => 'default terms footer notes', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/documents', 'section' => 'company_details', 'label' => 'documents', 'keywords' => 'files attachments uploads', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/custom_fields', 'section' => 'company_details', 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Localization.
            ['path' => '/settings/localization', 'section' => null, 'label' => 'localization', 'keywords' => 'language currency timezone date format region locale number', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/localization/custom_labels', 'section' => 'localization', 'label' => 'custom_labels', 'keywords' => 'translations rename labels wording', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Payments & taxes.
            ['path' => '/settings/online_payments', 'section' => null, 'label' => 'online_payments', 'keywords' => 'gateway stripe paypal gocardless mollie checkout braintree payment methods', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/gateways/create', 'section' => 'online_payments', 'label' => 'add_gateway', 'keywords' => 'gateway stripe paypal connect processor', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => null, 'label' => 'tax_settings', 'keywords' => 'vat gst sales tax rates', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_rates', 'section' => 'tax_settings', 'label' => 'tax_rates', 'keywords' => 'vat gst percentage', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/payment_terms', 'section' => 'online_payments', 'label' => 'payment_terms', 'keywords' => 'net due days terms', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Product / task / expense settings.
            ['path' => '/settings/product_settings', 'section' => null, 'label' => 'product_settings', 'keywords' => 'items inventory stock products', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => null, 'label' => 'task_settings', 'keywords' => 'time tracking timer', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_statuses', 'section' => 'task_settings', 'label' => 'task_statuses', 'keywords' => 'kanban columns workflow', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => null, 'label' => 'expense_settings', 'keywords' => 'vendor bills costs', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_categories', 'section' => 'expense_settings', 'label' => 'expense_categories', 'keywords' => 'categories grouping', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tags', 'section' => null, 'label' => 'tags', 'keywords' => 'labels keywords categorise', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Workflow.
            ['path' => '/settings/workflow_settings', 'section' => null, 'label' => 'workflow_settings', 'keywords' => 'automation auto bill email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Account management.
            ['path' => '/settings/account_management', 'section' => null, 'label' => 'account_management', 'keywords' => 'subscription plan billing account', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/overview', 'section' => 'account_management', 'label' => 'overview', 'keywords' => 'plan usage summary', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/enabled_modules', 'section' => 'account_management', 'label' => 'enabled_modules', 'keywords' => 'features toggle modules', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/integrations', 'section' => 'account_management', 'label' => 'integrations', 'keywords' => 'api zapier connect', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/security_settings', 'section' => 'account_management', 'label' => 'security_settings', 'keywords' => 'password 2fa sessions timeout security', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/referral_program', 'section' => 'account_management', 'label' => 'referral_program', 'keywords' => 'affiliate rewards referrals invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_HOSTED],
            ['path' => '/settings/account_management/danger_zone', 'section' => 'account_management', 'label' => 'danger_zone', 'keywords' => 'delete purge cancel account close', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Backup / restore / import / export.
            ['path' => '/settings/backup_restore', 'section' => null, 'label' => 'backup_restore', 'keywords' => 'export import json archive', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/backup_restore/restore', 'section' => 'backup_restore', 'label' => 'restore', 'keywords' => 'import recover upload', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/backup_restore/backup', 'section' => 'backup_restore', 'label' => 'backup', 'keywords' => 'export download archive', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => null, 'label' => 'import_export', 'keywords' => 'csv migration upload download', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Invoice design.
            ['path' => '/settings/invoice_design', 'section' => null, 'label' => 'invoice_design', 'keywords' => 'pdf template layout design theme', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Custom fields.
            ['path' => '/settings/custom_fields', 'section' => null, 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/company', 'section' => 'custom_fields', 'label' => 'company', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/clients', 'section' => 'custom_fields', 'label' => 'clients', 'keywords' => 'extra fields customers', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/products', 'section' => 'custom_fields', 'label' => 'products', 'keywords' => 'extra fields items', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/invoices', 'section' => 'custom_fields', 'label' => 'invoices', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/payments', 'section' => 'custom_fields', 'label' => 'payments', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/projects', 'section' => 'custom_fields', 'label' => 'projects', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/tasks', 'section' => 'custom_fields', 'label' => 'tasks', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/vendors', 'section' => 'custom_fields', 'label' => 'vendors', 'keywords' => 'extra fields suppliers', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/expenses', 'section' => 'custom_fields', 'label' => 'expenses', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/users', 'section' => 'custom_fields', 'label' => 'users', 'keywords' => 'extra fields staff', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Generated numbers.
            ['path' => '/settings/generated_numbers', 'section' => null, 'label' => 'generated_numbers', 'keywords' => 'invoice number counter pattern prefix sequence', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Client portal.
            ['path' => '/settings/client_portal', 'section' => null, 'label' => 'client_portal', 'keywords' => 'customer portal self service domain', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/authorization', 'section' => 'client_portal', 'label' => 'authorization', 'keywords' => 'login password protect access', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/registration', 'section' => 'client_portal', 'label' => 'registration', 'keywords' => 'signup self register', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/messages', 'section' => 'client_portal', 'label' => 'messages', 'keywords' => 'text terms policy footer', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/customize', 'section' => 'client_portal', 'label' => 'customize', 'keywords' => 'css branding styling header', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // E-Invoicing.
            ['path' => '/settings/e_invoice', 'section' => null, 'label' => 'e_invoice', 'keywords' => 'peppol einvoice e-invoice xml ubl storecove electronic', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Email.
            ['path' => '/settings/email_settings', 'section' => null, 'label' => 'email_settings', 'keywords' => 'smtp mailgun postmark sending from name reply to bcc', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => null, 'label' => 'templates_and_reminders', 'keywords' => 'reminders email templates dunning late fees', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Banking.
            ['path' => '/settings/bank_accounts', 'section' => null, 'label' => 'bank_accounts', 'keywords' => 'banking transactions feeds yodlee nordigen plaid', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/transaction_rules', 'section' => 'bank_accounts', 'label' => 'transaction_rules', 'keywords' => 'matching rules categorise transactions', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/transaction_rules/create', 'section' => 'bank_accounts', 'label' => 'new_transaction_rule', 'keywords' => 'add matching rule', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Groups / subscriptions / schedules / users.
            ['path' => '/settings/group_settings', 'section' => null, 'label' => 'group_settings', 'keywords' => 'groups client groups', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions', 'section' => null, 'label' => 'subscriptions', 'keywords' => 'recurring payment links plans checkout', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/schedules', 'section' => null, 'label' => 'schedules', 'keywords' => 'scheduled reports automation cron jobs', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/users', 'section' => null, 'label' => 'users', 'keywords' => 'team members staff permissions invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/system_logs', 'section' => null, 'label' => 'system_logs', 'keywords' => 'audit logs activity history events', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Integrations.
            ['path' => '/settings/integrations', 'section' => null, 'label' => 'integrations', 'keywords' => 'api zapier connect third party', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_tokens', 'section' => 'integrations', 'label' => 'api_tokens', 'keywords' => 'api keys secret token', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_webhooks', 'section' => 'integrations', 'label' => 'api_webhooks', 'keywords' => 'webhooks zapier callbacks events', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/analytics', 'section' => 'integrations', 'label' => 'analytics', 'keywords' => 'google analytics tracking measurement', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
        ];
    }
}
