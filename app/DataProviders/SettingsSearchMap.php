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
 *  - path:       the front-end route to navigate to. Every path resolves to a
 *                real, index-rendering route in the admin UI — list routes that
 *                only expose create/edit children (e.g. tax_rates) point at the
 *                parent settings page where the list actually renders.
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
            ['path' => '/settings/user_details/connect', 'section' => 'user_details', 'label' => 'connect', 'keywords' => 'oauth google microsoft apple gmail social login link', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/accent_color', 'section' => 'user_details', 'label' => 'accent_color', 'keywords' => 'theme colour color appearance', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/notifications', 'section' => 'user_details', 'label' => 'notifications', 'keywords' => 'alerts emails notify', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/enable_two_factor', 'section' => 'user_details', 'label' => 'enable_two_factor', 'keywords' => '2fa mfa totp authenticator security otp', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/custom_fields', 'section' => 'user_details', 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'preferences', 'keywords' => 'options settings', 'permission' => null, 'scope' => self::SCOPE_ALL],

            // Company details. The "Details" tab renders at the company_details index.
            ['path' => '/settings/company_details', 'section' => null, 'label' => 'company_details', 'keywords' => 'business organisation organization details name id number website', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/address', 'section' => 'company_details', 'label' => 'address', 'keywords' => 'street city postcode state country location', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/logo', 'section' => 'company_details', 'label' => 'logo', 'keywords' => 'branding image picture', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/defaults', 'section' => 'company_details', 'label' => 'defaults', 'keywords' => 'default terms footer notes', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/documents', 'section' => 'company_details', 'label' => 'documents', 'keywords' => 'files attachments uploads', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/custom_fields', 'section' => 'company_details', 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Localization.
            ['path' => '/settings/localization', 'section' => null, 'label' => 'localization', 'keywords' => 'language currency timezone date format region locale number first day of week', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/localization/custom_labels', 'section' => 'localization', 'label' => 'custom_labels', 'keywords' => 'translations rename labels wording', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Payments.
            ['path' => '/settings/online_payments', 'section' => null, 'label' => 'online_payments', 'keywords' => 'gateway stripe paypal gocardless mollie checkout braintree authorize razorpay payment methods', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/gateways/create', 'section' => 'online_payments', 'label' => 'add_gateway', 'keywords' => 'gateway stripe paypal connect processor new', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/payment_terms', 'section' => 'online_payments', 'label' => 'payment_terms', 'keywords' => 'net due days terms', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/payment_terms/create', 'section' => 'online_payments', 'label' => 'new_payment_term', 'keywords' => 'add net due days term', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Taxes. The tax rates list renders inside the tax settings page.
            ['path' => '/settings/tax_settings', 'section' => null, 'label' => 'tax_settings', 'keywords' => 'vat gst sales tax rates inclusive', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'tax_rates', 'keywords' => 'vat gst percentage rate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_rates/create', 'section' => 'tax_settings', 'label' => 'new_tax_rate', 'keywords' => 'add vat gst rate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Products.
            ['path' => '/settings/product_settings', 'section' => null, 'label' => 'product_settings', 'keywords' => 'items inventory stock products', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Tasks. The task statuses list renders inside the task settings page.
            ['path' => '/settings/task_settings', 'section' => null, 'label' => 'task_settings', 'keywords' => 'time tracking timer', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'task_statuses', 'keywords' => 'kanban columns workflow status', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_statuses/create', 'section' => 'task_settings', 'label' => 'new_task_status', 'keywords' => 'add kanban column status', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Expenses. The expense categories list renders inside the expense settings page.
            ['path' => '/settings/expense_settings', 'section' => null, 'label' => 'expense_settings', 'keywords' => 'vendor bills costs', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'expense_categories', 'keywords' => 'categories grouping', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_categories/create', 'section' => 'expense_settings', 'label' => 'new_expense_category', 'keywords' => 'add category', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Tags.
            ['path' => '/settings/tags', 'section' => null, 'label' => 'task_tags', 'keywords' => 'labels keywords categorise tags', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tags/projects', 'section' => 'tags', 'label' => 'project_tags', 'keywords' => 'labels keywords project tags', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Workflow.
            ['path' => '/settings/workflow_settings', 'section' => null, 'label' => 'workflow_settings', 'keywords' => 'automation auto bill email send', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Account management.
            ['path' => '/settings/account_management', 'section' => null, 'label' => 'account_management', 'keywords' => 'subscription plan billing account', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/overview', 'section' => 'account_management', 'label' => 'overview', 'keywords' => 'plan usage summary', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/enabled_modules', 'section' => 'account_management', 'label' => 'enabled_modules', 'keywords' => 'features toggle modules', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/integrations', 'section' => 'account_management', 'label' => 'integrations', 'keywords' => 'quickbooks qbo zapier slack google analytics api sync connect third party accounting', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/security_settings', 'section' => 'account_management', 'label' => 'security_settings', 'keywords' => 'password 2fa sessions timeout security', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/referral_program', 'section' => 'account_management', 'label' => 'referral_program', 'keywords' => 'affiliate rewards referrals invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_HOSTED],
            ['path' => '/settings/account_management/danger_zone', 'section' => 'account_management', 'label' => 'danger_zone', 'keywords' => 'delete purge cancel account close', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Backup / restore / import / export. Backup renders at the backup_restore index.
            ['path' => '/settings/backup_restore', 'section' => null, 'label' => 'backup_restore', 'keywords' => 'export import json archive backup download', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/backup_restore/restore', 'section' => 'backup_restore', 'label' => 'restore', 'keywords' => 'import recover upload', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => null, 'label' => 'import_export', 'keywords' => 'csv migration upload download quickbooks', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Invoice design.
            ['path' => '/settings/invoice_design', 'section' => null, 'label' => 'invoice_design', 'keywords' => 'pdf template layout design theme general fonts', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/custom_designs', 'section' => 'invoice_design', 'label' => 'custom_designs', 'keywords' => 'template html pdf design custom layout', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

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
            ['path' => '/settings/generated_numbers/clients', 'section' => 'generated_numbers', 'label' => 'clients', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/invoices', 'section' => 'generated_numbers', 'label' => 'invoices', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/recurring_invoices', 'section' => 'generated_numbers', 'label' => 'recurring_invoices', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/payments', 'section' => 'generated_numbers', 'label' => 'payments', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/quotes', 'section' => 'generated_numbers', 'label' => 'quotes', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/credits', 'section' => 'generated_numbers', 'label' => 'credits', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/projects', 'section' => 'generated_numbers', 'label' => 'projects', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/tasks', 'section' => 'generated_numbers', 'label' => 'tasks', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/vendors', 'section' => 'generated_numbers', 'label' => 'vendors', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/purchase_orders', 'section' => 'generated_numbers', 'label' => 'purchase_orders', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/expenses', 'section' => 'generated_numbers', 'label' => 'expenses', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/recurring_expenses', 'section' => 'generated_numbers', 'label' => 'recurring_expenses', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

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
            ['path' => '/settings/bank_accounts', 'section' => null, 'label' => 'bank_accounts', 'keywords' => 'banking transactions feeds yodlee nordigen plaid gocardless', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/create', 'section' => 'bank_accounts', 'label' => 'new_bank_account', 'keywords' => 'connect link banking add', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/transaction_rules', 'section' => 'bank_accounts', 'label' => 'transaction_rules', 'keywords' => 'matching rules categorise transactions', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/transaction_rules/create', 'section' => 'bank_accounts', 'label' => 'new_transaction_rule', 'keywords' => 'add matching rule', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Groups / subscriptions / schedules / users.
            ['path' => '/settings/group_settings', 'section' => null, 'label' => 'group_settings', 'keywords' => 'groups client groups', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/group_settings/create', 'section' => 'group_settings', 'label' => 'new_group', 'keywords' => 'add client group', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions', 'section' => null, 'label' => 'subscriptions', 'keywords' => 'recurring payment links plans checkout', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'subscriptions', 'label' => 'new_subscription', 'keywords' => 'add payment link plan', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/schedules', 'section' => null, 'label' => 'schedules', 'keywords' => 'scheduled reports automation cron jobs', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/schedules/create', 'section' => 'schedules', 'label' => 'new_schedule', 'keywords' => 'add scheduled report job', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/users', 'section' => null, 'label' => 'users', 'keywords' => 'team members staff permissions invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/users/create', 'section' => 'users', 'label' => 'new_user', 'keywords' => 'add team member staff invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/system_logs', 'section' => null, 'label' => 'system_logs', 'keywords' => 'audit logs activity history events', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Integrations. The bare /settings/integrations path has no index; api tokens,
            // webhooks and analytics each resolve to their own page.
            ['path' => '/settings/integrations/api_tokens', 'section' => 'integrations', 'label' => 'api_tokens', 'keywords' => 'api keys secret token', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_tokens/create', 'section' => 'integrations', 'label' => 'new_token', 'keywords' => 'add api key secret', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_webhooks', 'section' => 'integrations', 'label' => 'api_webhooks', 'keywords' => 'webhooks zapier callbacks events', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_webhooks/create', 'section' => 'integrations', 'label' => 'new_webhook', 'keywords' => 'add webhook callback', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/analytics', 'section' => 'integrations', 'label' => 'analytics', 'keywords' => 'google analytics tracking measurement', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
        ];
    }
}
