<?php


namespace App\Services\Migration\Steps;

/**
 * // TODO: Update this part.. Need to consult.
 * @package App\Services\Migration\Steps
 */
class SettingsStepService
{
    private $request;
    private $response;
    private $successful;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function start()
    {
        if (!in_array($this->request->settings, ['remove_everything', 'keep_settings'])) {
            $this->response = 'You have to chose one of available options.';
            $this->successful = false;

            return;
        }

        if ($this->request->settings == 'remove_everything') {
            $this->successful = true;
            $this->response = 'Awesome! Let\'s migrate the clients now. TODO: Create generator for "blank" company on V2.';

            return;
        }

        return $this->updateCompanyProperties();
    }

    public function getSuccessful()
    {
        return $this->successful;
    }

    public function onSuccess()
    {
        return '/migration/steps/clients';
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function onFailure()
    {
        return '/migration/steps/settings';
    }

    public function updateCompanyProperties()
    {
        $account = auth()->user();

        $data = [
            'company_key' => $account->account_key,
            'industry_id' => $account->industry_id,
            'size_id' => $account->size_id,
            'fill_products' => $account->fill_products,
            'update_products' => $account->update_products,
            'first_day_of_week' => $account->start_of_week,
            'convert_products' => $account->convert_products,
            'custom_fields' => $account->custom_fields,
            'settings' => [
                'name' => $account->name,
                'address1' => $account->address1,
                'address2' => $account->address2,
                'city' => $account->city,
                'state' => $account->state,
                'postal_code' => $account->postal_code,
                'country_id' => $account->country_id,
                'invoice_terms' => $account->invoice_terms,
                'invoice_taxes' => $account->invoice_taxes,
                'invoice_item_taxes' => $account->invoice_item_taxes,
                'invoice_design_id' => $account->invoice_design_id, // TODO: Implement design on v2.
                'phone' => $account->work_phone,
                'email' => $account->email,
                'language_id' => $account->language_id,
                'custom_value1' => $account->custom_value1,
                'custom_value2' => $account->custom_value2,
                'settings' => $account->hide_paid_to_date,
                'vat_number' => $account->vat_number,
                'invoice_number_counter' => $account->invoice_number_counter,
                // TODO: quote_number_pattern explanation.
                'quote_number_counter' => $account->quote_number_counter,
                'share_counter' => $account->shared_invoice_quote_counter,
                'id_number' => $account->id_number,
                'invoice_footer' => $account->invoice_footer,
                'pdf_email_attachment' => $account->pdf_email_attachment,
                'font_size' => $account->font_size,
                'invoice_labels' => $account->invoice_labels,
                'show_item_taxes' => $account->invoice_item_taxes,
                'military_time' => $account->military_time,
                'enable_reminder1' => $account->enable_reminder1,
                'enable_reminder2' => $account->enable_reminder2,
                'enable_reminder3' => $account->enable_reminder3,
                'num_days_reminder1' => $account->num_days_reminder1,
                'num_days_reminder2' => $account->num_days_reminder2,
                'num_days_reminder3' => $account->num_days_reminder3,
                'invoice_number_pattern' => $account->invoice_number_pattern,
                'quote_number_pattern' => $account->quote_number_pattern,
                'quote_terms' => $account->quote_terms,
                'enable_email_markup' => $account->enable_email_markup,
                'website' => $account->website,
                'auto_convert_quote' => $account->auto_convert_quote,
                'all_pages_footer' => $account->all_pages_footer,
                'all_pages_header' => $account->all_pages_header,
                'show_currency_code' => $account->show_currency_code,
                'enable_client_portal_password' => $account->enable_portal_password,
                'send_portal_password' => $account->send_portal_password,
                'enable_client_portal' => $account->enable_client_portal,
                'invoice_fields' => $account->invoice_fields,
                'company_logo' => $account->logo,
                'document_email_attachment' => $account->document_email_attachment,
                'enable_client_portal_dashboard' => $account->enable_client_portal_dashboard,

                // TODO: legacyninja.accounts.95: company_id?

                'page_size' => $account->page_size,

                // TODO:: enabled_tax_rates == enable_second_tax_rate ??

                'show_accept_invoice_terms' => $account->show_accept_invoice_terms,
                'show_accept_quote_terms' => $account->show_accept_quote_terms,
                'require_invoice_signature' => $account->require_invoice_signature,
                'require_quote_signature' => $account->require_quote_signature,
                'client_number_counter' => $account->client_number_counter,
                'client_number_pattern' => $account->client_number_pattern,
                'payment_terms' => $account->payment_terms,
                'reset_counter_frequency_id' => $account->reset_counter_frequency_id,
                'reset_counter_date' => $account->reset_counter_date,
                'payment_type_id' => $account->payment_type_id,
                'tax_name1' => $account->tax_name1,
                'tax_name2' => $account->tax_name2,
                'tax_name3' => $account->tax_name3,
                'tax_rate1' => $account->tax_rate1,
                'tax_rate2' => $account->tax_rate2,
                'tax_rate3' => $account->tax_rate3,
                'quote_design_id' => $account->quote_design_id,
                'credit_number_counter' => $account->credit_number_counter,
                'credit_number_pattern' => $account->credit_number_pattern,
                'default_task_rate' => $account->tax_rate,
                'inclusive_taxes' => $account->inclusive_taxes,
                'signature_on_pdf' => $account->signature_on_pdf,
                'ubl_email_attachment' => $account->ubl_email_attachment,
                'auto_archive_invoice' => $account->auto_archive_invoice,
                'auto_archive_quote' => $account->auto_archive_quote,
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-API-SECRET' => session('x_api_secret'),
            'X-API-TOKEN' => session('x_api_token'),
        ];

        $response = \Unirest\Request::get(
            session('self_hosted_url') . '/api/v1/companies',
            $headers,
            json_encode($data)
        );

        if ($response->code == 200) {
            $this->successful = true;
            $this->response = 'Settings migrated successfully. Now let\'s do the same for clients.';
        }

        if ($response->code == 500) {
            $this->successful = false;
            throw new \Exception($response->body->message);
        }

        return true;
    }
}