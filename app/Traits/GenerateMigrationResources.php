<?php

namespace App\Traits;

use App\Models\AccountGateway;
use App\Models\AccountGatewaySettings;
use App\Models\AccountGatewayToken;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

trait GenerateMigrationResources
{
    protected $account;

    protected function getCompany()
    {
        return [
            'account_id' => $this->account->id,
            'industry_id' => $this->account->industry_id,
            'ip' => $this->account->ip,
            'company_key' => $this->account->account_key,
            'logo' => $this->account->logo,
            'convert_products' => $this->account->convert_products,
            'fill_products' => $this->account->fill_products,
            'update_products' => $this->account->update_products,
            'show_product_details' => $this->account->show_product_notes,
            'custom_surcharge_taxes1' => $this->account->custom_invoice_taxes1,
            'custom_surcharge_taxes2' => $this->account->custom_invoice_taxes2,
            'subdomain' => $this->account->subdomain,
            'size_id' => $this->account->size_id,
            'enable_modules' => $this->account->enabled_modules,
            'custom_fields' => $this->account->custom_fields,
            'created_at' => $this->account->created_at ? $this->account->created_at->toDateString() : null,
            'updated_at' => $this->account->updated_at ? $this->account->updated_at->toDateString() : null,
            'settings' => $this->getCompanySettings(),
        ];
    }

    public function getCompanySettings()
    {
        return [
            'timezone_id' => $this->account->timezone_id ? (string) $this->account->timezone_id : '15',
            'date_format_id' => $this->account->date_format_id ? (string) $this->account->date_format_id : '1',
            'currency_id' => $this->account->currency_id ? (string) $this->account->currency_id : '1',
            'name' => $this->account->name ?: trans('texts.untitled'),
            'address1' => $this->account->address1 ?: '',
            'address2' => $this->account->address2 ?: '',
            'city' => $this->account->city ?: '',
            'state' => $this->account->state ?: '',
            'postal_code' => $this->account->postal_code ?: '',
            'country_id' => $this->account->country_id ? (string) $this->account->country_id : '840',
            'invoice_terms' => $this->account->invoice_terms ?: '',
            'enabled_item_tax_rates' => $this->account->invoice_item_taxes ? 2 : 0,
            'invoice_design_id' => $this->account->invoice_design_id ?: (string) $this->account->invoice_design_id ?: '1',
            'phone' => $this->account->work_phone ?: '',
            'email' => $this->account->work_email ?: '',
            'language_id' => $this->account->language_id ? (string) $this->account->language_id : '1',
            'custom_value1' => $this->account->custom_value1 ? (string) $this->account->custom_value1 : '',
            'custom_value2' => $this->account->custom_value2 ? (string) $this->account->custom_value2 : '',
            'custom_value3' => '',
            'custom_value4' => '',
            'hide_paid_to_date' => $this->account->hide_paid_to_date ? (bool) $this->account->hide_paid_to_date : false,
            'vat_number' => $this->account->vat_number ?: '',
            'shared_invoice_quote_counter' => $this->account->share_counter ? (bool) $this->account->share_counter : true,
            'id_number' => $this->account->id_number ?: '',
            'invoice_footer' => $this->account->invoice_footer ?: '',
            'pdf_email_attachment' => $this->account->pdf_email_attachment ? (bool) $this->account->pdf_email_attachment : false,
            'font_size' => $this->account->font_size ?: 9,
            'invoice_labels' => $this->account->invoice_labels ?: '',
            'military_time' => $this->account->military_time ? (bool) $this->account->military_time : false,
            'invoice_number_pattern' => $this->account->invoice_number_pattern ?: '',
            'quote_number_pattern' => $this->account->quote_number_pattern ?: '',
            'quote_terms' => $this->account->quote_terms ?: '',
            'website' => $this->account->website ?: '',
            'auto_convert_quote' => $this->account->auto_convert_quote ? (bool) $this->account->auto_convert_quote : false,
            'all_pages_footer' => $this->account->all_pages_footer ? (bool) $this->account->all_pages_footer : true,
            'all_pages_header' => $this->account->all_pages_header ? (bool) $this->account->all_pages_header : true,
            'show_currency_code' => $this->account->show_currency_code ? (bool) $this->account->show_currency_code : false,
            'enable_client_portal_password' => $this->account->enable_portal_password ? (bool) $this->account->enable_portal_password : true,
            'send_portal_password' => $this->account->send_portal_password ? (bool) $this->account->send_portal_password : false,
            'recurring_number_prefix' => $this->account->recurring_invoice_number_prefix ? $this->account->recurring_invoice_number_prefix : 'R',
            'enable_client_portal' => $this->account->enable_client_portal ? (bool) $this->account->enable_client_portal : false,
            'invoice_fields' => $this->account->invoice_fields ?: '',
            'company_logo' => $this->account->logo ?: '',
            'embed_documents' => $this->account->invoice_embed_documents ? (bool) $this->account->invoice_embed_documents : false,
            'document_email_attachment' => $this->account->document_email_attachment ? (bool) $this->account->document_email_attachment : false,
            'enable_client_portal_dashboard' => $this->account->enable_client_portal_dashboard ? (bool) $this->account->enable_client_portal_dashboard : true,
            'page_size' => $this->account->page_size ?: 'A4',
            'show_accept_invoice_terms' => $this->account->show_accept_invoice_terms ? (bool) $this->account->show_accept_invoice_terms : false,
            'show_accept_quote_terms' => $this->account->show_accept_quote_terms ? (bool) $this->account->show_accept_quote_terms : false,
            'require_invoice_signature' => $this->account->require_invoice_signature ? (bool) $this->account->require_invoice_signature : false,
            'require_quote_signature' => $this->account->require_quote_signature ? (bool) $this->account->require_quote_signature : false,
            'client_number_counter' => $this->account->client_number_counter ?: 0,
            'client_number_pattern' => $this->account->client_number_pattern ?: '',
            'payment_number_pattern' => '',
            'payment_number_counter' => 0,
            'payment_terms' => $this->account->payment_terms ?: '',
            'reset_counter_frequency_id' => $this->account->reset_counter_frequency_id ? (string) $this->account->reset_counter_frequency_id : '0',
            'payment_type_id' => $this->account->payment_type_id ? (string) $this->account->payment_type_id : '1',
            'reset_counter_date' => $this->account->reset_counter_date ?: '',
            'tax_name1' => $this->account->tax_name1 ?: '',
            'tax_rate1' => $this->account->tax_rate1 ?: 0,
            'tax_name2' => $this->account->tax_name2 ?: '',
            'tax_rate2' => $this->account->tax_rate2 ?: 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'quote_design_id' => $this->account->quote_design_id ? (string) $this->account->quote_design_id : '1',
            'credit_number_counter' => $this->account->credit_number_counter ?: 0,
            'credit_number_pattern' => $this->account->credit_number_pattern ?: '',
            'default_task_rate' => $this->account->task_rate ?: 0,
            'inclusive_taxes' => $this->account->inclusive_taxes ? (bool) $this->account->inclusive_taxes : false,
            'signature_on_pdf' => $this->account->signature_on_pdf ? (bool) $this->account->signature_on_pdf : false,
            'ubl_email_attachment' => $this->account->ubl_email_attachment ? (bool) $this->account->ubl_email_attachment : false,
            'auto_archive_invoice' => $this->account->auto_archive_invoice ? (bool) $this->account->auto_archive_invoice : false,
            'auto_archive_quote' => $this->account->auto_archive_quote ? (bool) $this->account->auto_archive_quote : false,
            'auto_email_invoice' => $this->account->auto_email_invoice ? (bool) $this->account->auto_email_invoice : false,
            'counter_padding' => $this->account->invoice_number_padding ?: 4,
        ];
    }

    public function getTaxRates()
    {
        $rates = TaxRate::where('account_id', $this->account->id)
            ->withTrashed()
            ->get();

        $transformed = [];

        foreach ($rates as $rate) {
            $transformed[] = [
                'name' => $rate->name,
                'rate' => $rate->rate,
                'company_id' => $rate->account_id,
                'user_id' => $rate->user_id,
                'created_at' => $rate->created_at ? $rate->created_at->toDateString() : null,
                'updated_at' => $rate->updated_at ? $rate->updated_at->toDateString() : null,
                'deleted_at' => $rate->deleted_at ? $rate->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    protected function getClients()
    {
        $clients = [];

        foreach ($this->account->clients()->withTrashed()->get() as $client) {
            $clients[] = [
                'id' => $client->id,
                'company_id' => $client->account_id,
                'user_id' => $client->user_id,
                'name' => $client->name,
                'balance' => $client->balance,
                'paid_to_date' => $client->paid_to_date,
                'address1' => $client->address1,
                'address2' => $client->address2,
                'city' => $client->city,
                'state' => $client->state,
                'postal_code' => $client->postal_code,
                'country_id' => $client->country_id,
                'phone' => $client->work_phone,
                'private_notes' => $client->private_notes,
                'website' => $client->website,
                'industry_id' => $client->industry_id,
                'size_id' => $client->size_id,
                'is_deleted' => $client->is_deleted,
                'vat_number' => $client->vat_number,
                'id_number' => $client->id_number,
                'custom_value1' => $client->custom_value1,
                'custom_value2' => $client->custom_value2,
                'shipping_address1' => $client->shipping_address1,
                'shipping_address2' => $client->shipping_address2,
                'shipping_city' => $client->shipping_city,
                'shipping_state' => $client->shipping_state,
                'shipping_postal_code' => $client->shipping_postal_code,
                'shipping_country_id' => $client->shipping_country_id,
                'contacts' => $this->getClientContacts($client->contacts),
                'settings' => $this->getClientSettings($client),
            ];
        }

        return $clients;
    }

    private function getClientSettings($client)
    {
        $settings = new \stdClass();
        $settings->currency_id = $client->currency_id ? (string) $client->currency_id : (string) $client->account->currency_id;

        if ($client->language_id) {
            $settings->language_id = $client->language_id;
        }

        return $settings;
    }

    protected function getClientContacts($contacts)
    {
        $transformed = [];

        foreach ($contacts as $contact) {
            $transformed[] = [
                'id' => $contact->id,
                'company_id' => $contact->account_id,
                'user_id' => $contact->user_id,
                'client_id' => $contact->client_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'phone' => $contact->phone,
                'custom_value1' => $contact->custom_value1,
                'custom_value2' => $contact->custom_value2,
                'email' => $contact->email,
                'is_primary' => $contact->is_primary,
                'send_email' => $contact->send_invoice,
                'confirmed' => $contact->confirmation_token ? true : false,
                'last_login' => $contact->last_login,
                'password' => $contact->password,
                'remember_token' => $contact->remember_token,
                'contact_key' => $contact->contact_key,
            ];
        }

        return $transformed;
    }

    protected function getProducts()
    {
        $products = Product::where('account_id', $this->account->id)
            ->withTrashed()
            ->get();

        $transformed = [];

        foreach ($products as $product) {
            $transformed[] = [
                'company_id' => $product->account_id,
                'user_id' => $product->user_id,
                'custom_value1' => $product->custom_value1,
                'custom_value2' => $product->custom_value2,
                'product_key' => $product->product_key,
                'notes' => $product->notes,
                'cost' => $product->cost,
                'quantity' => $product->qty,
                'tax_name1' => $product->tax_name1,
                'tax_name2' => $product->tax_name2,
                'tax_rate1' => $product->tax_rate1,
                'tax_rate2' => $product->tax_rate2,
                'created_at' => $product->created_at ? $product->created_at->toDateString() : null,
                'updated_at' => $product->updated_at ? $product->updated_at->toDateString() : null,
                'deleted_at' => $product->deleted_at ? $product->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    public function getUsers()
    {
        $users = User::where('account_id', $this->account->id)
            ->withTrashed()
            ->get();

        $transformed = [];

        foreach ($users as $user) {
            $transformed[] = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'confirmation_code' => $user->confirmation_code,
                'failed_logins' => $user->failed_logins,
                'referral_code' => $user->referral_code,
                'oauth_user_id' => $user->oauth_user_id,
                'oauth_provider_id' => $user->oauth_provider_id,
                'google_2fa_secret' => $user->google_2fa_secret,
                'accepted_terms_version' => $user->accepted_terms_version,
                'password' => $user->password,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at ? $user->created_at->toDateString() : null,
                'updated_at' => $user->updated_at ? $user->updated_at->toDateString() : null,
                'deleted_at' => $user->deleted_at ? $user->deleted_at->toDateString() : null,
                'company_user' => [],
            ];
        }

        return $transformed;
    }

    private function getCreditsNotes()
    {
        $credits = [];

        $export_credits = Invoice::where('account_id', $this->account->id)
            ->where('amount', '<', '0')
            ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
            ->withTrashed()
            ->get();

        foreach ($export_credits as $credit) {
            $credits[] = [
                'id' => $credit->id,
                'client_id' => $credit->client_id,
                'user_id' => $credit->user_id,
                'company_id' => $credit->account_id,
                'status_id' => $credit->invoice_status_id,
                'design_id' => $credit->invoice_design_id,
                'number' => $credit->invoice_number,
                'discount' => $credit->discount,
                'is_amount_discount' => $credit->is_amount_discount ?: false,
                'po_number' => $credit->po_number,
                'date' => $credit->invoice_date,
                'last_sent_date' => $credit->last_sent_date,
                'due_date' => $credit->due_date,
                'uses_inclusive_taxes' => $this->account->inclusive_taxes,
                'is_deleted' => $credit->is_deleted,
                'footer' => $credit->invoice_footer,
                'public_notes' => $credit->public_notes,
                'private_notes' => $credit->private_notes,
                'terms' => $credit->terms,
                'tax_name1' => $credit->tax_name1,
                'tax_name2' => $credit->tax_name2,
                'tax_rate1' => $credit->tax_rate1,
                'tax_rate2' => $credit->tax_rate2,
                'custom_value1' => $credit->custom_value1,
                'custom_value2' => $credit->custom_value2,
                'next_send_date' => null,
                'amount' => $credit->amount,
                'balance' => $credit->balance,
                'partial' => $credit->partial,
                'partial_due_date' => $credit->partial_due_date,
                'line_items' => $this->getInvoiceItems($credit->invoice_items),
                'created_at' => $credit->created_at ? $credit->created_at->toDateString() : null,
                'updated_at' => $credit->updated_at ? $credit->updated_at->toDateString() : null,
                'deleted_at' => $credit->deleted_at ? $credit->deleted_at->toDateString() : null,
            ];
        }

        return $credits;
    }

    protected function getInvoices()
    {
        $invoices = [];

        $export_invoices = Invoice::where('account_id', $this->account->id)
            ->where('amount', '>=', '0')
            ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
            ->withTrashed()
            ->get();

        foreach ($export_invoices as $invoice) {
            $invoices[] = [
                'id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'user_id' => $invoice->user_id,
                'company_id' => $invoice->account_id,
                'status_id' => $this->transformStatusId($invoice->invoice_status_id),
                'design_id' => $invoice->invoice_design_id,
                'number' => $invoice->invoice_number,
                'discount' => $invoice->discount,
                'is_amount_discount' => $invoice->is_amount_discount ?: false,
                'po_number' => $invoice->po_number,
                'date' => $invoice->invoice_date,
                'last_sent_date' => $invoice->last_sent_date,
                'due_date' => $invoice->due_date,
                'uses_inclusive_taxes' => $this->account->inclusive_taxes,
                'is_deleted' => $invoice->is_deleted,
                'footer' => $invoice->invoice_footer,
                'public_notes' => $invoice->public_notes,
                'private_notes' => $invoice->private_notes,
                'terms' => $invoice->terms,
                'tax_name1' => $invoice->tax_name1,
                'tax_name2' => $invoice->tax_name2,
                'tax_rate1' => $invoice->tax_rate1,
                'tax_rate2' => $invoice->tax_rate2,
                'custom_value1' => $invoice->custom_value1,
                'custom_value2' => $invoice->custom_value2,
                'next_send_date' => null,
                'amount' => $invoice->amount,
                'balance' => $invoice->balance,
                'partial' => $invoice->partial,
                'partial_due_date' => $invoice->partial_due_date,
                'line_items' => $this->getInvoiceItems($invoice->invoice_items),
                'created_at' => $invoice->created_at ? $invoice->created_at->toDateString() : null,
                'updated_at' => $invoice->updated_at ? $invoice->updated_at->toDateString() : null,
                'deleted_at' => $invoice->deleted_at ? $invoice->deleted_at->toDateString() : null,
                'invitations' => $this->getResourceInvitations($invoice->invitations, 'invoice_id'),
            ];
        }

        return $invoices;
    }

    /*
    define('INVOICE_STATUS_DRAFT', 1);
    define('INVOICE_STATUS_SENT', 2);
    define('INVOICE_STATUS_VIEWED', 3);
    define('INVOICE_STATUS_APPROVED', 4);
    define('INVOICE_STATUS_PARTIAL', 5);
    define('INVOICE_STATUS_PAID', 6);
    define('INVOICE_STATUS_OVERDUE', -1);
    define('INVOICE_STATUS_UNPAID', -2);

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_PARTIAL = 3;
    const STATUS_PAID = 4;
    const STATUS_CANCELLED = 5;
    const STATUS_REVERSED = 6;
     */
    private function transformStatusId($status)
    {
        switch ($status) {
            case 1:
                return 1;
                break;
            case 2:
                return 2;
                break;
            case 3:
                return 2;
                break;
            case 4:
              return 2;
                break;
            case 5:
                return 3;
                break;
            case 6:
                return 4;
                break;
            default:
                return 2;
                break;
        }
    }

    public function getResourceInvitations($items, $resourceKeyId)
    {
        $transformed = [];

        foreach ($items as $invitation) {
            $transformed[] = [
                'id' => $invitation->id,
                'company_id' => $invitation->account_id,
                'user_id' => $invitation->user_id,
                'client_contact_id' => $invitation->contact_id,
                $resourceKeyId => $invitation->invoice_id,
                'key' => $invitation->invitation_key,
                'transaction_reference' => $invitation->transaction_reference,
                'message_id' => $invitation->message_id,
                'email_error' => $invitation->email_error,
                'signature_base64' => $invitation->signature_base64,
                'signature_date' => $invitation->signature_date,
                'sent_date' => $invitation->sent_date,
                'viewed_date' => $invitation->viewed_date,
                'opened_date' => $invitation->opened_date,
                'created_at' => $invitation->created_at ? $invitation->created_at->toDateString() : null,
                'updated_at' => $invitation->updated_at ? $invitation->updated_at->toDateString() : null,
                'deleted_at' => $invitation->deleted_at ? $invitation->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    public function getInvoiceItems($items)
    {
        $transformed = [];

        foreach ($items as $item) {
            $transformed[] = [
                'id' => $item->id,
                'quantity' => (float) $item->qty,
                'cost' => (float) $item->cost,
                'product_key' => $item->product_key,
                'notes' => $item->notes,
                'discount' => (float) $item->discount,
                'tax_name1' => $item->tax_name1,
                'tax_rate1' => (float) $item->tax_rate1,
                'date' => $item->created_at,
                'custom_value1' => $item->custom_value1,
                'custom_value2' => $item->custom_value2,
                'line_item_type_id' => $item->invoice_item_type_id,
            ];
        }

        return $transformed;
    }

    public function getQuotes()
    {
        $transformed = [];

        $quotes = Invoice::where('account_id', $this->account->id)
            ->where('invoice_type_id', '=', INVOICE_TYPE_QUOTE)
            ->withTrashed()
            ->get();

        foreach ($quotes as $quote) {
            $transformed[] = [
                'id' => $quote->id,
                'client_id' => $quote->client_id,
                'user_id' => $quote->user_id,
                'company_id' => $quote->account_id,
                'status_id' => $quote->invoice_status_id,
                'design_id' => $quote->invoice_design_id,
                'number' => $quote->invoice_number,
                'discount' => $quote->discount,
                'is_amount_discount' => $quote->is_amount_discount ?: false,
                'po_number' => $quote->po_number,
                'date' => $quote->invoice_date,
                'last_sent_date' => $quote->last_sent_date,
                'due_date' => $quote->due_date,
                'uses_inclusive_taxes' => $this->account->inclusive_taxes,
                'is_deleted' => $quote->is_deleted,
                'footer' => $quote->invoice_footer,
                'public_notes' => $quote->public_notes,
                'private_notes' => $quote->private_notes,
                'terms' => $quote->terms,
                'tax_name1' => $quote->tax_name1,
                'tax_name2' => $quote->tax_name2,
                'tax_rate1' => $quote->tax_rate1,
                'tax_rate2' => $quote->tax_rate2,
                'custom_value1' => $quote->custom_value1,
                'custom_value2' => $quote->custom_value2,
                'next_send_date' => null,
                'amount' => $quote->amount,
                'balance' => $quote->balance,
                'partial' => $quote->partial,
                'partial_due_date' => $quote->partial_due_date,
                'line_items' => $this->getInvoiceItems($quote->invoice_items),
                'created_at' => $quote->created_at ? $quote->created_at->toDateString() : null,
                'updated_at' => $quote->updated_at ? $quote->updated_at->toDateString() : null,
                'deleted_at' => $quote->deleted_at ? $quote->deleted_at->toDateString() : null,
                'invitations' => $this->getResourceInvitations($quote->invitations, 'quote_id'),
            ];
        }

        return $transformed;
    }

    /*
    const STATUS_DRAFT = 1;
    const STATUS_SENT =  2;
    const STATUS_APPROVED = 3;
    const STATUS_EXPIRED = -1;
     */
    private function transformQuoteStatus($status)
    {
        switch ($status) {
            case 1:
                return 1;
                break;
            case 2:
                return 2;
                break;
            case 4:
                return 3;
                break;

            default:
                return 2;
                break;
        }
    }

    public function getPayments()
    {
        $transformed = [];

        $payments = Payment::where('account_id', $this->account->id)
            ->withTrashed()
            ->get();

        foreach ($payments as $payment) {
            $transformed[] = [
                'id' => $payment->id,
                'invoices' => [
                    ['invoice_id' => $payment->invoice_id, 'amount' => $payment->amount, 'refunded' => $payment->refunded],
                ],
                'invoice_id' => $payment->invoice_id,
                'company_id' => $payment->account_id,
                'client_id' => $payment->client_id,
                'user_id' => $payment->user_id,
                'client_contact_id' => $payment->contact_id,
                'invitation_id' => $payment->invitation_id,
                'company_gateway_id' => $payment->account_gateway_id,
                'type_id' => $payment->payment_type_id,
                'status_id' => $payment->payment_status_id,
                'amount' => $payment->amount,
                'applied' => $payment->amount,
                'refunded' => $payment->refunded,
                'date' => $payment->payment_date,
                'transaction_reference' => $payment->transaction_reference,
                'payer_id' => $payment->payer_id,
                'is_deleted' => $payment->is_deleted,
                'exchange_rate' => $payment->exchange_rate ? number_format((float) $payment->exchange_rate, 6) : null,
                'exchange_currency_id' => $payment->exchange_currency_id,
                'currency_id' => isset($payment->client->currency->id) ? $payment->client->currency->id : $this->account->currency_id,
                'updated_at' => $payment->updated_at ? $payment->updated_at->toDateString() : null,
                'created_at' => $payment->created_at ? $payment->created_at->toDateString() : null,
                'deleted_at' => $payment->deleted_at ? $payment->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    private function getCredits()
    {
        $credits = Credit::where('account_id', $this->account->id)->where('balance', '>', '0')->whereIsDeleted(false)
            ->withTrashed()
            ->get();

        $transformed = [];

        foreach ($credits as $credit) {
            $transformed[] = [
                'client_id' => $credit->client_id,
                'user_id' => $credit->user_id,
                'company_id' => $credit->account_id,
                'is_deleted' => $credit->is_deleted,
                'amount' => $credit->balance,
                'applied' => 0,
                'refunded' => 0,
                'date' => $credit->date,
                'created_at' => $credit->created_at ? $credit->created_at->toDateString() : null,
                'updated_at' => $credit->updated_at ? $credit->updated_at->toDateString() : null,
                'deleted_at' => $credit->deleted_at ? $credit->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    private function getDocuments()
    {
        $documents = Document::where('account_id', $this->account->id)->get();

        $transformed = [];

        foreach ($documents as $document) {
            $transformed[] = [
                'id' => $document->id,
                'user_id' => $document->user_id,
                'company_id' => $this->account->id,
                'invoice_id' => $document->invoice_id,
                'expense_id' => $document->expense_id,
                'path' => $document->path,
                'preview' => $document->preview,
                'name' => $document->name,
                'type' => $document->type,
                'disk' => $document->disk,
                'hash' => $document->hash,
                'size' => $document->size,
                'width' => $document->width,
                'height' => $document->height,
                'created_at' => $document->created_at ? $document->created_at->toDateString() : null,
                'updated_at' => $document->updated_at ? $document->updated_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    private function getCompanyGateways()
    {
        $account_gateways = AccountGateway::where('account_id', $this->account->id)->get();

        $transformed = [];

        foreach ($account_gateways as $account_gateway) {
            $gateway_types = $account_gateway->paymentDriver()->gatewayTypes();

            foreach ($gateway_types as $gateway_type_id) {
                $transformed[] = [
                    'id' => $account_gateway->id,
                    'user_id' => $account_gateway->user_id,
                    'gateway_key' => $this->getGatewayKeyById($account_gateway->gateway_id),
                    'accepted_credit_cards' => $account_gateway->accepted_credit_cards,
                    'require_cvv' => $account_gateway->require_cvv,
                    'show_billing_address' => $account_gateway->show_billing_address,
                    'show_shipping_address' => $account_gateway->show_shipping_address,
                    'update_details' => $account_gateway->update_details,
                    'config' => Crypt::decrypt($account_gateway->config),
                    'fees_and_limits' => $this->transformFeesAndLimits($gateway_type_id),
                    'custom_value1' => '',
                    'custom_value2' => '',
                    'custom_value3' => '',
                    'custom_value4' => '',
                ];
            }
        }

        return $transformed;
    }

    private function getClientGatewayTokens()
    {
        $payment_methods = PaymentMethod::where('account_id', $this->account->id)->get();

        $transformed = [];

        $is_default = true;

        foreach ($payment_methods as $payment_method) {
            $contact = Contact::find($payment_method->contact_id)->first();
            $agt = AccountGatewayToken::find($payment_method->account_gateway_token_id)->first();

            $transformed[] = [
                'id' => $payment_method->id,
                'company_id' => $this->account->id,
                'client_id' => $contact->client_id,
                'token' => $payment_method->source_reference,
                'company_gateway_id' => $agt->account_gateway_id,
                'gateway_customer_reference' => $agt->token,
                'gateway_type_id' => $payment_method->payment_type->gateway_type_id,
                'is_default' => $is_default,
                'meta' => $this->convertMeta($payment_method),
            ];

            $is_default = false;
        }

        return $transformed;
    }

    private function getPaymentTerms()
    {
        $payment_terms = PaymentTerm::where('account_id', 0)->orWhere('account_id', $this->account->id)->get();

        $transformed = [];

        foreach($payment_terms as $payment_term)
        {

            if($payment_term->num_days == -1)
                $payment_term->num_days = 0;

            $transformed[] = [
                'user_id' => 0,
                'company_id' => $this->account->id,
                'num_days' => $payment_term->num_days,
                'deleted_at' => $payment_term->deleted_at,
            ];

        }

        return $transformed;
    }

    private function convertMeta($payment_method)
    {
        $expiry = explode('-', $payment_method->expiration);

        if (is_array($expiry)) {
            $exp_month = $expiry[1];
            $exp_year = $expiry[0];
        } else {
            $exp_month = '';
            $exp_year = '';
        }

        $meta = new \stdClass();
        $meta->exp_month = $exp_month;
        $meta->exp_year = $exp_year;
        $meta->brand = $payment_method->payment_type->name;
        $meta->last4 = str_replace(',', '', ($payment_method->expiration));
        $meta->type = $payment_method->payment_type->gateway_type_id;

        return $meta;
    }

    private function transformFeesAndLimits($gateway_type_id)
    {
        $ags = AccountGatewaySettings::where('account_id', $this->account->id)
            ->where('gateway_type_id', $gateway_type_id)
            ->first();

        if (! $ags) {
            return new \stdClass();
        }

        $fees_and_limits = new \stdClass();
        $fees_and_limits->min_limit = $ags->min_limit;
        $fees_and_limits->max_limit = $ags->max_limit;
        $fees_and_limits->fee_amount = $ags->fee_amount;
        $fees_and_limits->fee_percent = $ags->fee_percent;
        $fees_and_limits->tax_name1 = $ags->tax_name1;
        $fees_and_limits->tax_rate1 = $ags->tax_rate1;
        $fees_and_limits->tax_name2 = $ags->tax_name2;
        $fees_and_limits->tax_rate2 = $ags->tax_rate2;
        $fees_and_limits->tax_name3 = '';
        $fees_and_limits->tax_rate3 = 0;

        return $fees_and_limits;
    }

    private function getGatewayKeyById($gateway_id)
    {
        $gateways = [
            ['id' => 1, 'key' => '3b6621f970ab18887c4f6dca78d3f8bb'],
            ['id' => 2, 'key' => '46c5c1fed2c43acf4f379bae9c8b9f76'],
            ['id' => 3, 'key' => '944c20175bbe6b9972c05bcfe294c2c7'],
            ['id' => 4, 'key' => '4e0ed0d34552e6cb433506d1ac03a418'],
            ['id' => 5, 'key' => '513cdc81444c87c4b07258bc2858d3fa'],
            ['id' => 6, 'key' => '99c2a271b5088951334d1302e038c01a'],
            ['id' => 7, 'key' => '1bd651fb213ca0c9d66ae3c336dc77e8'],
            ['id' => 8, 'key' => 'c3dec814e14cbd7d86abd92ce6789f8c'],
            ['id' => 9, 'key' => '070dffc5ca94f4e66216e44028ebd52d'],
            ['id' => 10, 'key' => '334d419939c06bd99b4dfd8a49243f0f'],
            ['id' => 11, 'key' => 'd6814fc83f45d2935e7777071e629ef9'],
            ['id' => 12, 'key' => '0d97c97d227f91c5d0cb86d01e4a52c9'],
            ['id' => 13, 'key' => 'a66b7062f4c8212d2c428209a34aa6bf'],
            ['id' => 14, 'key' => '7e6fc08b89467518a5953a4839f8baba'],
            ['id' => 15, 'key' => '38f2c48af60c7dd69e04248cbb24c36e'],
            ['id' => 16, 'key' => '80af24a6a69f5c0bbec33e930ab40665'],
            ['id' => 17, 'key' => '0749cb92a6b36c88bd9ff8aabd2efcab'],
            ['id' => 18, 'key' => '4c8f4e5d0f353a122045eb9a60cc0f2d'],
            ['id' => 19, 'key' => '8036a5aadb2bdaafb23502da8790b6a2'],
            ['id' => 20, 'key' => 'd14dd26a37cecc30fdd65700bfb55b23'],
            ['id' => 21, 'key' => 'd14dd26a37cdcc30fdd65700bfb55b23'],
            ['id' => 22, 'key' => 'ea3b328bd72d381387281c3bd83bd97c'],
            ['id' => 23, 'key' => 'a0035fc0d87c4950fb82c73e2fcb825a'],
            ['id' => 24, 'key' => '16dc1d3c8a865425421f64463faaf768'],
            ['id' => 25, 'key' => '43e639234f660d581ddac725ba7bcd29'],
            ['id' => 26, 'key' => '2f71dc17b0158ac30a7ae0839799e888'],
            ['id' => 27, 'key' => '733998ee4760b10f11fb48652571e02c'],
            ['id' => 28, 'key' => '6312879223e49c5cf92e194646bdee8f'],
            ['id' => 29, 'key' => '106ef7e7da9062b0df363903b455711c'],
            ['id' => 30, 'key' => 'e9a38f0896b5b82d196be3b7020c8664'],
            ['id' => 31, 'key' => '0da4e18ed44a5bd5c8ec354d0ab7b301'],
            ['id' => 32, 'key' => 'd3979e62eb603fbdf1c78fe3a8ba7009'],
            ['id' => 33, 'key' => '557d98977e7ec02dfa53de4b69b335be'],
            ['id' => 34, 'key' => '54dc60c869a7322d87efbec5c0c25805'],
            ['id' => 35, 'key' => 'e4a02f0a4b235eb5e9e294730703bb74'],
            ['id' => 36, 'key' => '1b3c6f3ccfea4f5e7eadeae188cccd7f'],
            ['id' => 37, 'key' => '7cba6ce5c125f9cb47ea8443ae671b68'],
            ['id' => 38, 'key' => 'b98cfa5f750e16cee3524b7b7e78fbf6'],
            ['id' => 39, 'key' => '3758e7f7c6f4cecf0f4f348b9a00f456'],
            ['id' => 40, 'key' => 'cbc7ef7c99d31ec05492fbcb37208263'],
            ['id' => 41, 'key' => 'e186a98d3b079028a73390bdc11bdb82'],
            ['id' => 42, 'key' => '761040aca40f685d1ab55e2084b30670'],
            ['id' => 43, 'key' => '1b2cef0e8c800204a29f33953aaf3360'],
            ['id' => 44, 'key' => '7ea2d40ecb1eb69ef8c3d03e5019028a'],
            ['id' => 45, 'key' => '70ab90cd6c5c1ab13208b3cef51c0894'],
            ['id' => 46, 'key' => 'bbd736b3254b0aabed6ad7fda1298c88'],
            ['id' => 47, 'key' => '231cb401487b9f15babe04b1ac4f7a27'],
            ['id' => 48, 'key' => 'bad8699d581d9fa040e59c0bb721a76c'],
            ['id' => 49, 'key' => '8fdeed552015b3c7b44ed6c8ebd9e992'],
            ['id' => 50, 'key' => 'f7ec488676d310683fb51802d076d713'],
            ['id' => 51, 'key' => '30334a52fb698046572c627ca10412e8'],
            ['id' => 52, 'key' => 'b9886f9257f0c6ee7c302f1c74475f6c'],
            ['id' => 53, 'key' => 'ef498756b54db63c143af0ec433da803'],
            ['id' => 54, 'key' => 'ca52f618a39367a4c944098ebf977e1c'],
            ['id' => 55, 'key' => '54faab2ab6e3223dbe848b1686490baa'],
        ];

        return $gateways[$gateway_id]['key'];
    }
}
