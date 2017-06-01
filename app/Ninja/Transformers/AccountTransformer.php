<?php

namespace App\Ninja\Transformers;

use App\Models\Account;

/**
 * Class AccountTransformer.
 */
class AccountTransformer extends EntityTransformer
{
    /**
     * @var array
     */
    protected $defaultIncludes = [
        'users',
        'products',
        'tax_rates',
        'expense_categories',
        'projects',
        'account_email_settings',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'clients',
        'invoices',
        'payments',
    ];

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeAccountEmailSettings(Account $account)
    {
        $transformer = new AccountEmailSettingsTransformer($account, $this->serializer);

        return $this->includeItem($account->account_email_settings, $transformer, 'account_email_settings');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeExpenseCategories(Account $account)
    {
        $transformer = new ExpenseCategoryTransformer($account, $this->serializer);

        return $this->includeCollection($account->expense_categories, $transformer, 'expense_categories');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeProjects(Account $account)
    {
        $transformer = new ProjectTransformer($account, $this->serializer);

        return $this->includeCollection($account->projects, $transformer, 'projects');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeUsers(Account $account)
    {
        $transformer = new UserTransformer($account, $this->serializer);

        return $this->includeCollection($account->users, $transformer, 'users');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeClients(Account $account)
    {
        $transformer = new ClientTransformer($account, $this->serializer);

        return $this->includeCollection($account->clients, $transformer, 'clients');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeInvoices(Account $account)
    {
        $transformer = new InvoiceTransformer($account, $this->serializer);

        return $this->includeCollection($account->invoices, $transformer, 'invoices');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeProducts(Account $account)
    {
        $transformer = new ProductTransformer($account, $this->serializer);

        return $this->includeCollection($account->products, $transformer, 'products');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeTaxRates(Account $account)
    {
        $transformer = new TaxRateTransformer($account, $this->serializer);

        return $this->includeCollection($account->tax_rates, $transformer, 'taxRates');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includePayments(Account $account)
    {
        $transformer = new PaymentTransformer($account, $this->serializer);

        return $this->includeCollection($account->payments, $transformer, 'payments');
    }

    /**
     * @param Account $account
     *
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     *
     * @return array
     */
    public function transform(Account $account)
    {
        return [
            'account_key' => $account->account_key,
            'logo' => $account->logo,
            'name' => $account->present()->name,
            'id_number' => $account->id_number,
            'currency_id' => (int) $account->currency_id,
            'timezone_id' => (int) $account->timezone_id,
            'date_format_id' => (int) $account->date_format_id,
            'datetime_format_id' => (int) $account->datetime_format_id,
            'updated_at' => $this->getTimestamp($account->updated_at),
            'archived_at' => $this->getTimestamp($account->deleted_at),
            'address1' => $account->address1,
            'address2' => $account->address2,
            'city' => $account->city,
            'state' => $account->state,
            'postal_code' => $account->postal_code,
            'country_id' => (int) $account->country_id,
            'invoice_terms' => $account->invoice_terms,
            'email_footer' => $account->email_footer,
            'industry_id' => (int) $account->industry_id,
            'size_id' => (int) $account->size_id,
            'invoice_taxes' => (bool) $account->invoice_taxes,
            'invoice_item_taxes' => (bool) $account->invoice_item_taxes,
            'invoice_design_id' => (int) $account->invoice_design_id,
            'quote_design_id' => (int) $account->quote_design_id,
            'client_view_css' => (string) $account->client_view_css,
            'work_phone' => $account->work_phone,
            'work_email' => $account->work_email,
            'language_id' => (int) $account->language_id,
            'fill_products' => (bool) $account->fill_products,
            'update_products' => (bool) $account->update_products,
            'vat_number' => $account->vat_number,
            'custom_invoice_label1' => $account->custom_invoice_label1,
            'custom_invoice_label2' => $account->custom_invoice_label2,
            'custom_invoice_taxes1' => $account->custom_invoice_taxes1,
            'custom_invoice_taxes2' => $account->custom_invoice_taxes1,
            'custom_label1' => $account->custom_label1,
            'custom_label2' => $account->custom_label2,
            'custom_value1' => $account->custom_value1,
            'custom_value2' => $account->custom_value2,
            'primary_color' => $account->primary_color,
            'secondary_color' => $account->secondary_color,
            'custom_client_label1' => $account->custom_client_label1,
            'custom_client_label2' => $account->custom_client_label2,
            'hide_quantity' => (bool) $account->hide_quantity,
            'hide_paid_to_date' => (bool) $account->hide_paid_to_date,
            'invoice_number_prefix' => $account->invoice_number_prefix,
            'invoice_number_counter' => $account->invoice_number_counter,
            'quote_number_prefix' => $account->quote_number_prefix,
            'quote_number_counter' => $account->quote_number_counter,
            'share_counter' => (bool) $account->share_counter,
            'token_billing_type_id' => (int) $account->token_billing_type_id,
            'invoice_footer' => $account->invoice_footer,
            'pdf_email_attachment' => (bool) $account->pdf_email_attachment,
            'font_size' => $account->font_size,
            'invoice_labels' => $account->invoice_labels,
            'custom_design' => $account->custom_design,
            'show_item_taxes' => (bool) $account->show_item_taxes,
            'military_time' => (bool) $account->military_time,
            'enable_reminder1' => $account->enable_reminder1,
            'enable_reminder2' => $account->enable_reminder2,
            'enable_reminder3' => $account->enable_reminder3,
            'num_days_reminder1' => $account->num_days_reminder1,
            'num_days_reminder2' => $account->num_days_reminder2,
            'num_days_reminder3' => $account->num_days_reminder3,
            'custom_invoice_text_label1' => $account->custom_invoice_text_label1,
            'custom_invoice_text_label2' => $account->custom_invoice_text_label2,
            'tax_name1' => $account->tax_name1 ?: '',
            'tax_rate1' => (float) $account->tax_rate1,
            'tax_name2' => $account->tax_name2 ?: '',
            'tax_rate2' => (float) $account->tax_rate2,
            'recurring_hour' => $account->recurring_hour,
            'invoice_number_pattern' => $account->invoice_number_pattern,
            'quote_number_pattern' => $account->quote_number_pattern,
            'quote_terms' => $account->quote_terms,
            'email_design_id' => $account->email_design_id,
            'enable_email_markup' => (bool) $account->enable_email_markup,
            'website' => $account->website,
            'direction_reminder1' => (int) $account->direction_reminder1,
            'direction_reminder2' => (int) $account->direction_reminder2,
            'direction_reminder3' => (int) $account->direction_reminder3,
            'field_reminder1' => (int) $account->field_reminder1,
            'field_reminder2' => (int) $account->field_reminder2,
            'field_reminder3' => (int) $account->field_reminder3,
            'header_font_id' => (int) $account->header_font_id,
            'body_font_id' => (int) $account->body_font_id,
            'auto_convert_quote' => (bool) $account->auto_convert_quote,
            'all_pages_footer' => (bool) $account->all_pages_footer,
            'all_pages_header' => (bool) $account->all_pages_header,
            'show_currency_code' => (bool) $account->show_currency_code,
            'enable_portal_password' => (bool) $account->enable_portal_password,
            'send_portal_password' => (bool) $account->send_portal_password,
            'custom_invoice_item_label1' => $account->custom_invoice_item_label1,
            'custom_invoice_item_label2' => $account->custom_invoice_item_label2,
            'recurring_invoice_number_prefix' => $account->recurring_invoice_number_prefix,
            'enable_client_portal' => (bool) $account->enable_client_portal,
            'invoice_fields' => $account->invoice_fields,
            'invoice_embed_documents' => (bool) $account->invoice_embed_documents,
            'document_email_attachment' => (bool) $account->document_email_attachment,
            'enable_client_portal_dashboard' => (bool) $account->enable_client_portal_dashboard,
            'page_size' => $account->page_size,
            'live_preview' => (bool) $account->live_preview,
            'invoice_number_padding' => (int) $account->invoice_number_padding,
            'enable_second_tax_rate' => (bool) $account->enable_second_tax_rate,
            'auto_bill_on_due_date' => (bool) $account->auto_bill_on_due_date,
            'start_of_week' => $account->start_of_week,
            'enable_buy_now_buttons' => (bool) $account->enable_buy_now_buttons,
            'include_item_taxes_inline' => (bool) $account->include_item_taxes_inline,
            'financial_year_start' => $account->financial_year_start,
            'enabled_modules' => (int) $account->enabled_modules,
            'enabled_dashboard_sections' => (int) $account->enabled_dashboard_sections,
            'show_accept_invoice_terms' => (bool) $account->show_accept_invoice_terms,
            'show_accept_quote_terms' => (bool) $account->show_accept_quote_terms,
            'require_invoice_signature' => (bool) $account->require_invoice_signature,
            'require_quote_signature' => (bool) $account->require_quote_signature,
            'client_number_prefix' => $account->client_number_prefix,
            'client_number_counter' => (int) $account->client_number_counter,
            'client_number_pattern' => $account->client_number_pattern,
            'payment_terms' => (int) $account->payment_terms,
            'reset_counter_frequency_id' => (int) $account->reset_counter_frequency_id,
            'payment_type_id' => (int) $account->payment_type_id,
            'gateway_fee_enabled' => (bool) $account->gateway_fee_enabled,
            'reset_counter_date' => $account->reset_counter_date,
            'custom_contact_label1' => $account->custom_contact_label1,
            'custom_contact_label2' => $account->custom_contact_label2,
        ];
    }
}
