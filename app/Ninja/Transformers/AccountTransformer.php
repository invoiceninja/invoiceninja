<?php

namespace App\Ninja\Transformers;

use App\Models\Account;

/**
 * Class AccountTransformer.
 */
class AccountTransformer extends EntityTransformer
{
	  /**
     * @SWG\Property(property="account_key", type="string", example="123456")
     * @SWG\Property(property="logo", type="string", example="Logo")
     * @SWG\Property(property="name", type="string", example="John Doe")
     * @SWG\Property(property="id_number", type="string", example="123456")
     * @SWG\Property(property="currency_id", type="integer", example=1)
     * @SWG\Property(property="timezone_id", type="integer", example=1)
     * @SWG\Property(property="date_format_id", type="integer", example=1)
     * @SWG\Property(property="datetime_format_id", type="integer", example=1)
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="address1", type="string", example="10 Main St.")
     * @SWG\Property(property="address2", type="string", example="1st Floor")
     * @SWG\Property(property="city", type="string", example="New York")
     * @SWG\Property(property="state", type="string", example="NY")
     * @SWG\Property(property="postal_code", type="string", example=10010)
     * @SWG\Property(property="country_id", type="integer", example=840)
     * @SWG\Property(property="invoice_terms", type="string", example="Terms")
     * @SWG\Property(property="industry_id", type="integer", example=1)
     * @SWG\Property(property="size_id", type="integer", example=1)
     * @SWG\Property(property="invoice_taxes", type="boolean", example=false)
     * @SWG\Property(property="invoice_item_taxes", type="boolean", example=false)
     * @SWG\Property(property="invoice_design_id", type="integer", example=1)
     * @SWG\Property(property="quote_design_id", type="integer", example=1)
     * @SWG\Property(property="client_view_css", type="string", example="CSS")
     * @SWG\Property(property="work_phone", type="string", example="(212) 555-1212")
     * @SWG\Property(property="work_email", type="string", example="john.doe@company.com")
     * @SWG\Property(property="language_id", type="integer", example=1)
     * @SWG\Property(property="fill_products", type="boolean", example=false)
     * @SWG\Property(property="update_products", type="boolean", example=false)
     * @SWG\Property(property="vat_number", type="string", example="123456")
     * @SWG\Property(property="custom_value1", type="string", example="Value")
     * @SWG\Property(property="custom_value2", type="string", example="Value")
     * @SWG\Property(property="primary_color", type="string", example="Color")
     * @SWG\Property(property="secondary_color", type="string", example="Color")
     * @SWG\Property(property="hide_quantity", type="boolean", example=false)
     * @SWG\Property(property="hide_paid_to_date", type="boolean", example=false)
     * @SWG\Property(property="invoice_number_prefix", type="string", example="Invoice Number Prefix")
     * @SWG\Property(property="invoice_number_counter", type="integer", example=1)
     * @SWG\Property(property="quote_number_prefix", type="string", example="Quote Number Prefix")
     * @SWG\Property(property="quote_number_counter", type="integer", example=1)
     * @SWG\Property(property="share_counter", type="boolean", example=false)
     * @SWG\Property(property="token_billing_type_id", type="integer", example=1)
     * @SWG\Property(property="invoice_footer", type="string", example="Footer")
     * @SWG\Property(property="pdf_email_attachment", type="boolean", example=false)
     * @SWG\Property(property="font_size", type="string", example="14")
     * @SWG\Property(property="invoice_labels", type="string", example="Labels")
     * @SWG\Property(property="custom_design1", type="string", example="Design")
     * @SWG\Property(property="custom_design2", type="string", example="Design")
     * @SWG\Property(property="custom_design3", type="string", example="Design")
     * @SWG\Property(property="show_item_taxes", type="boolean", example=false)
     * @SWG\Property(property="military_time", type="boolean", example=false)
     * @SWG\Property(property="tax_name1", type="string", example="VAT")
     * @SWG\Property(property="tax_name2", type="string", example="Upkeep")
     * @SWG\Property(property="tax_rate1", type="number", format="float", example="17.5")
     * @SWG\Property(property="tax_rate2", type="number", format="float", example="30.0")
     * @SWG\Property(property="recurring_hour", type="string", example="Recurring Hour")
     * @SWG\Property(property="invoice_number_pattern", type="string", example="Invoice Number Pattern")
     * @SWG\Property(property="quote_number_pattern", type="string", example="Quote Number Pattern")
     * @SWG\Property(property="quote_terms", type="string", example="Labels")
     * @SWG\Property(property="website", type="string", example="http://www.example.com")
     * @SWG\Property(property="header_font_id", type="integer", example=1)
     * @SWG\Property(property="body_font_id", type="integer", example=1)
     * @SWG\Property(property="auto_convert_quote", type="boolean", example=false)
     * @SWG\Property(property="auto_archive_quote", type="boolean", example=false)
     * @SWG\Property(property="auto_archive_invoice", type="boolean", example=false)
     * @SWG\Property(property="auto_email_invoice", type="boolean", example=false)
     * @SWG\Property(property="all_pages_footer", type="boolean", example=false)
     * @SWG\Property(property="all_pages_header", type="boolean", example=false)
     * @SWG\Property(property="show_currency_code", type="boolean", example=false)
     * @SWG\Property(property="enable_portal_password", type="boolean", example=false)
     * @SWG\Property(property="send_portal_password", type="boolean", example=false)
     * @SWG\Property(property="recurring_invoice_number_prefix", type="string", example="Recurring Invoice Number Prefix")
     * @SWG\Property(property="enable_client_portal", type="boolean", example=false)
     * @SWG\Property(property="invoice_fields", type="string", example="Invoice Fields")
     * @SWG\Property(property="invoice_embed_documents", type="boolean", example=false)
     * @SWG\Property(property="document_email_attachment", type="boolean", example=false)
     * @SWG\Property(property="enable_client_portal_dashboard", type="boolean", example=false)
     * @SWG\Property(property="page_size", type="string", example="Page Size")
     * @SWG\Property(property="live_preview", type="boolean", example=false)
     * @SWG\Property(property="invoice_number_padding", type="integer", example=1)
     * @SWG\Property(property="enable_second_tax_rate", type="boolean", example=false)
     * @SWG\Property(property="auto_bill_on_due_date", type="boolean", example=false)
     * @SWG\Property(property="start_of_week", type="string", example="Monday")
     * @SWG\Property(property="enable_buy_now_buttons", type="boolean", example=false)
     * @SWG\Property(property="include_item_taxes_inline", type="boolean", example=false)
     * @SWG\Property(property="financial_year_start", type="string", example="January")
     * @SWG\Property(property="enabled_modules", type="integer", example=1)
     * @SWG\Property(property="enabled_dashboard_sections", type="integer", example=1)
     * @SWG\Property(property="show_accept_invoice_terms", type="boolean", example=false)
     * @SWG\Property(property="show_accept_quote_terms", type="boolean", example=false)
     * @SWG\Property(property="require_invoice_signature", type="boolean", example=false)
     * @SWG\Property(property="require_quote_signature", type="boolean", example=false)
     * @SWG\Property(property="client_number_prefix", type="string", example="Client Number Prefix")
     * @SWG\Property(property="client_number_counter", type="integer", example=1)
     * @SWG\Property(property="client_number_pattern", type="string", example="Client Number Pattern")
     * @SWG\Property(property="payment_terms", type="integer", example=1)
     * @SWG\Property(property="reset_counter_frequency_id", type="integer", example=1)
     * @SWG\Property(property="payment_type_id", type="integer", example=1)
     * @SWG\Property(property="gateway_fee_enabled", type="boolean", example=false)
     * @SWG\Property(property="send_item_details", type="boolean", example=false)
     * @SWG\Property(property="reset_counter_date", type="string", format="date", example="2018-01-01")
     * @SWG\Property(property="task_rate", type="number", format="float", example="17.5")
     * @SWG\Property(property="inclusive_taxes", type="boolean", example=false)
     * @SWG\Property(property="convert_products", type="boolean", example=false)
     * @SWG\Property(property="signature_on_pdf", type="boolean", example=false)
     * @SWG\Property(property="custom_invoice_taxes1", type="string", example="Value")
     * @SWG\Property(property="custom_invoice_taxes2", type="string", example="Value")
     * @SWG\Property(property="custom_fields", type="string", example="Field")
     * @SWG\Property(property="custom_messages", type="string", example="Message")
     * @SWG\Property(property="custom_invoice_label1", type="string", example="Label")
     * @SWG\Property(property="custom_invoice_label2", type="string", example="Label")
     * @SWG\Property(property="custom_client_label1", type="string", example="Label")
     * @SWG\Property(property="custom_client_label2", type="string", example="Label")
     * @SWG\Property(property="custom_contact_label1", type="string", example="Label")
     * @SWG\Property(property="custom_contact_label2", type="string", example="Label")
     * @SWG\Property(property="custom_label1", type="string", example="Label")
     * @SWG\Property(property="custom_label2", type="string", example="Label")
     * @SWG\Property(property="custom_invoice_text_label1", type="string", example="Label")
     * @SWG\Property(property="custom_invoice_text_label2", type="string", example="Label")
     * @SWG\Property(property="custom_invoice_item_label1", type="string", example="Label")
     * @SWG\Property(property="custom_invoice_item_label2", type="string", example="Label")
     */

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
            'custom_value1' => $account->custom_value1,
            'custom_value2' => $account->custom_value2,
            'primary_color' => $account->primary_color,
            'secondary_color' => $account->secondary_color,
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
            'custom_design1' => $account->custom_design1,
            'custom_design2' => $account->custom_design2,
            'custom_design3' => $account->custom_design3,
            'show_item_taxes' => (bool) $account->show_item_taxes,
            'military_time' => (bool) $account->military_time,
            'tax_name1' => $account->tax_name1 ?: '',
            'tax_rate1' => (float) $account->tax_rate1,
            'tax_name2' => $account->tax_name2 ?: '',
            'tax_rate2' => (float) $account->tax_rate2,
            'recurring_hour' => $account->recurring_hour,
            'invoice_number_pattern' => $account->invoice_number_pattern,
            'quote_number_pattern' => $account->quote_number_pattern,
            'quote_terms' => $account->quote_terms,
            'enable_email_markup' => (bool) $account->enable_email_markup,
            'website' => $account->website,
            'header_font_id' => (int) $account->header_font_id,
            'body_font_id' => (int) $account->body_font_id,
            'auto_convert_quote' => (bool) $account->auto_convert_quote,
            'auto_archive_quote' => (bool) $account->auto_archive_quote,
            'auto_archive_invoice' => (bool) $account->auto_archive_invoice,
            'auto_email_invoice' => (bool) $account->auto_email_invoice,
            'all_pages_footer' => (bool) $account->all_pages_footer,
            'all_pages_header' => (bool) $account->all_pages_header,
            'show_currency_code' => (bool) $account->show_currency_code,
            'enable_portal_password' => (bool) $account->enable_portal_password,
            'send_portal_password' => (bool) $account->send_portal_password,
            'recurring_invoice_number_prefix' => $account->recurring_invoice_number_prefix,
            'enable_client_portal' => (bool) $account->enable_client_portal,
            'invoice_fields' => $account->invoice_fields,
            'invoice_embed_documents' => (bool) $account->invoice_embed_documents,
            'document_email_attachment' => (bool) $account->document_email_attachment,
            'enable_client_portal_dashboard' => (bool) $account->enable_client_portal_dashboard,
            'page_size' => $account->page_size,
            'live_preview' => (bool) $account->live_preview,
            'realtime_preview' => (bool) $account->realtime_preview,
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
            'send_item_details' => (bool) $account->send_item_details,
            'reset_counter_date' => $account->reset_counter_date,
            'task_rate' => (float) $account->task_rate,
            'inclusive_taxes' => (bool) $account->inclusive_taxes,
            'convert_products' => (bool) $account->convert_products,
            'signature_on_pdf' => (bool) $account->signature_on_pdf,
            'custom_invoice_taxes1' => $account->custom_invoice_taxes1,
            'custom_invoice_taxes2' => $account->custom_invoice_taxes1,
            'custom_fields' => $account->custom_fields,
            'custom_messages' => $account->custom_messages,
            'custom_invoice_label1' => $account->customLabel('invoice1'),
            'custom_invoice_label2' => $account->customLabel('invoice2'),
            'custom_client_label1' => $account->customLabel('client1'),
            'custom_client_label2' => $account->customLabel('client2'),
            'custom_contact_label1' => $account->customLabel('contact1'),
            'custom_contact_label2' => $account->customLabel('contact2'),
            'custom_label1' => $account->customLabel('account1'),
            'custom_label2' => $account->customLabel('account2'),
            'custom_invoice_text_label1' => $account->customLabel('invoice_text1'),
            'custom_invoice_text_label2' => $account->customLabel('invoice_text2'),
            'custom_invoice_item_label1' => $account->customLabel('product1'),
            'custom_invoice_item_label2' => $account->customLabel('product2'),
        ];
    }
}
