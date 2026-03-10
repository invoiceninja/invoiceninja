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

namespace App\Services\Client;

use App\Models\CompanyGateway;
use App\Models\Client;
use App\Services\AbstractService;
use App\Utils\Traits\MakesHash;

class MapSettings extends AbstractService
{
    use MakesHash;

    /**
     * Maps setting keys to translation keys ONLY for properties
     * that don't have a matching translation key.
     *
     * Format: "setting_property" => "translation_key"
     *
     * @var array<string, string>
     */
    private array $default_settings = [
        // Client Portal
        "enable_client_portal_tasks" => "client_portal_tasks",
        "show_all_tasks_client_portal" => "show_tasks",
        "enable_client_portal_password" => "enable_portal_password",
        "portal_design_id" => "design",
        "client_portal_terms" => "terms_of_service",
        "client_portal_privacy_policy" => "privacy_policy",
        "client_portal_enable_uploads" => "client_document_upload",
        "client_portal_allow_under_payment" => "allow_under_payment",
        "client_portal_under_payment_minimum" => "minimum_payment",
        "client_portal_allow_over_payment" => "allow_over_payment",
        "vendor_portal_enable_uploads" => "vendor_document_upload",
        "portal_custom_head" => "header",
        "portal_custom_css" => "custom_css",
        "portal_custom_footer" => "footer",
        "portal_custom_js" => "custom_js",
        "client_can_register" => "register",

        // Payment Gateways & Notifications
        "company_gateway_ids" => "company_gateways",
        "client_online_payment_notification" => "online_payment_email",
        "client_manual_payment_notification" => "manual_payment_email",
        "send_email_on_mark_paid" => "mark_paid_payment_email",
        "client_initiated_payments_minimum" => "minimum_payment",
        "accept_client_input_quote_approval" => "accept_purchase_order_number",

        // Timing & Billing
        "entity_send_time" => "send_time",
        "auto_bill_date" => "auto_bill_on",
        "counter_number_applied" => "applied",
        "quote_number_applied" => "applied",
        "reminder_send_time" => "send_time",
        "payment_flow" => "checkout",

        // Number Patterns & Counters (those without direct match)
        "recurring_invoice_number_pattern" => "recurring_invoice_number",
        "recurring_invoice_number_counter" => "number_counter",
        "recurring_expense_number_pattern" => "expense_number_pattern",
        "recurring_expense_number_counter" => "number_counter",
        "recurring_quote_number_pattern" => "quote_number_pattern",
        "recurring_quote_number_counter" => "number_counter",
        "project_number_pattern" => "project_number",
        "project_number_counter" => "number_counter",
        "purchase_order_number_pattern" => "purchase_order_number",
        "purchase_order_number_counter" => "number_counter",
        "recurring_number_prefix" => "recurring_prefix",
        "reset_counter_frequency_id" => "reset_counter_date",

        // Design IDs
        "quote_design_id" => "quote_design",
        "credit_design_id" => "credit_design",
        "purchase_order_design_id" => "purchase_order_design",
        "delivery_note_design_id" => "delivery_note_design",
        "statement_design_id" => "statement_design",
        "payment_receipt_design_id" => "payment_receipt_design",
        "payment_refund_design_id" => "payment_refund_design",

        // Invoice/Document Settings
        "invoice_taxes" => "taxes",
        "purchase_order_public_notes" => "public_notes",
        "embed_documents" => "invoice_embed_documents",
        "hide_empty_columns_on_pdf" => "show_or_hide",
        "company_logo_size" => "logo_size",
        "sync_invoice_quote_columns" => "share_invoice_quote_columns",

        // Email Settings
        "email_sending_method" => "method",
        "gmail_sending_user_id" => "select_email_provider",
        "email_from_name" => "from_name",
        "custom_sending_email" => "from_email",
        "postmark_secret" => "postmark",
        "mailgun_secret" => "mailgun_private_key",
        "mailgun_endpoint" => "mailgun_domain",
        "brevo_secret" => "brevo_private_key",
        "ses_region" => "region",
        "ses_topic_arn" => "ses_topic_arn_help",

        // Email Subjects
        "email_subject_credit" => "credit_email",
        "email_subject_statement" => "statement",
        "email_subject_purchase_order" => "purchase_order",
        "email_subject_reminder1" => "first_reminder",
        "email_subject_reminder2" => "second_reminder",
        "email_subject_reminder3" => "third_reminder",
        "email_subject_reminder_endless" => "endless_reminder",
        "email_subject_custom1" => "custom1",
        "email_subject_custom2" => "custom2",
        "email_subject_custom3" => "custom3",
        "email_subject_payment_failed" => "payment_failed",

        // Email Templates
        "email_template_invoice" => "invoice_email",
        "email_template_quote" => "quote_email",
        "email_template_credit" => "credit_email",
        "email_template_payment" => "payment_email",
        "email_template_payment_partial" => "payment_partial",
        "email_template_statement" => "statement",
        "email_template_purchase_order" => "purchase_order",
        "email_template_reminder1" => "first_reminder",
        "email_template_reminder2" => "second_reminder",
        "email_template_reminder3" => "third_reminder",
        "email_template_reminder_endless" => "endless_reminder",
        "email_template_custom1" => "custom1",
        "email_template_custom2" => "custom2",
        "email_template_custom3" => "custom3",
        "email_template_payment_failed" => "payment_failed",

        // Reminders
        "enable_reminder1" => "first_reminder",
        "enable_reminder2" => "second_reminder",
        "enable_reminder3" => "third_reminder",
        "enable_reminder_endless" => "reminder_endless",
        "num_days_reminder1" => "first_reminder",
        "num_days_reminder2" => "second_reminder",
        "num_days_reminder3" => "third_reminder",
        "schedule_reminder1" => "first_reminder",
        "schedule_reminder2" => "second_reminder",
        "schedule_reminder3" => "third_reminder",
        "endless_reminder_frequency_id" => "endless_reminder",

        // Late Fees
        "late_fee_amount1" => "late_fee_amount",
        "late_fee_amount2" => "late_fee_amount",
        "late_fee_amount3" => "late_fee_amount",
        "late_fee_percent1" => "late_fee_percent",
        "late_fee_percent2" => "late_fee_percent",
        "late_fee_percent3" => "late_fee_percent",
        "late_fee_endless_amount" => "late_fee_amount",
        "late_fee_endless_percent" => "late_fee_percent",

        // Quote Reminders
        "email_quote_template_reminder1" => "quote_reminder1",
        "email_quote_subject_reminder1" => "quote_reminder1",
        "enable_quote_reminder1" => "quote_reminder1",
        "quote_num_days_reminder1" => "quote_reminder1",
        "quote_schedule_reminder1" => "quote_reminder1",
        "quote_late_fee_amount1" => "late_fee_amount",
        "quote_late_fee_percent1" => "late_fee_percent",

        // Payment & Credits
        "use_credits_payment" => "use_available_credits",
        "use_unapplied_payment" => "unapplied",
        "default_expense_payment_type_id" => "expense_payment_type",

        // Rounding
        "enable_rappen_rounding" => "rappen_rounding",
        "task_round_up" => "round_up",
    ];

    public function __construct(private Client $client) {}

    public function run()
    {
        $settings_map = [];
        $group_only_settings = [];
        $client_settings = (array) $this->client->settings;

        if ($this->client->group_settings) {
            $group_settings = (array) $this->client->group_settings->settings;
            $group_only_settings = array_diff_key($group_settings, $client_settings);
        }

        $group = $this->mapSettings($group_only_settings);

        unset($client_settings['entity']);
        unset($client_settings['industry_id']);
        unset($client_settings['size_id']);
        unset($client_settings['currency_id']);

        $client = $this->mapSettings($client_settings);

        return [
            'group_settings' => $group,
            'client_settings' => $client,
        ];

    }

    private function mapSettings(array $settings): array
    {

        return collect($settings)->mapWithKeys(function ($value, $key) {

            if ($key == "company_gateway_ids") {
                $key = "company_gateways";
                $value = $this->handleCompanyGateways($value);
            }

            if ($key == "language_id") {
                $value = $this->handleLanguage($value);
            }

            return [$this->getTranslationFromKey($key) => $value];
        })->toArray();

    }
    private function handleLanguage(?string $language_id): string
    {
        /** @var \App\Models\Language $language */
        $language = app('languages')->firstWhere('id', $language_id ?? '1');
        return $language->name;
    }

    private function getTranslationFromKey(string $key): string
    {
        if (isset($this->default_settings[$key])) {
            return ctrans("texts.{$this->default_settings[$key]}");
        }

        return ctrans("texts.{$key}");
    }

    private function handleCompanyGateways(?string $company_gateway_ids): string
    {
        if (!$company_gateway_ids) {
            return "No Special Configuration.";
        }

        if ($company_gateway_ids == "0") {
            return "Payment Gateways Disabled!";
        }

        if ($company_gateway_ids == "") {
            return "No Special Configuration.";
        }

        $company_gateway_ids = explode(',', $company_gateway_ids);

        $company_gateways = CompanyGateway::where('company_id', $this->client->company_id)
                                            ->whereIn('id', $this->transformKeys($company_gateway_ids))
                                            ->pluck('label')
                                            ->toArray();

        return implode(', ', $company_gateways);
    }
}
