<?php

namespace App\Http\Controllers\Migration;

use App\Models\Credit;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
use App\Libraries\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\BaseController;

class StepsController extends BaseController
{
    private $account;

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function start()
    {
        return view('migration.start');
    }

    public function import()
    {
        return view('migration.import');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function download()
    {
        return view('migration.download');
    }

    /**
     * Handle data downloading for the migration.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleDownload()
    {
        $this->account = Auth::user()->account;

        $date = date('Y-m-d');
        $accountKey = $this->account->account_key;

        $output = fopen('php://output', 'w') or Utils::fatalError();

        $fileName = "{$accountKey}-{$date}-invoiceninja";

        $data = [
            'company' => $this->getCompany(),
            'users' => $this->getUsers(),
            'tax_rates' => $this->getTaxRates(),
            'clients' => $this->getClients(),
            'products' => $this->getProducts(),
            'invoices' => $this->getInvoices(),
            'quotes' => $this->getQuotes(),
            'payments' => array_merge($this->getPayments(), $this->getCredits()),
            'credits' => $this->getCreditsNotes(),
        ];

        $file = storage_path("{$fileName}.zip");

        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('migration.json', json_encode($data));
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($file));
        header("Content-Disposition: attachment; filename={$fileName}.zip");

        readfile($file);
        unlink($file);

        return response()->json($data);
    }

    /**
     * Export company and map to the v2 fields.
     *
     * @return array
     */
    protected function getCompany()
    {
        // Notes: show_product_details, show_product_cost
        // What to do with: enabled_tax_rates, enable_product_costs, enable_product_quantity, portal_mode, portal_domain,

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
            'enable_invoice_quantity' => !$this->account->hide_quantity,
            'subdomain' => $this->account->subdomain,
            'size_id' => $this->account->size_id,
            'enable_modules' => $this->account->enabled_modules,
            'custom_fields' => $this->account->custom_fields,
            'created_at' => $this->account->created_at ? $this->account->created_at->toDateString() : null,
            'updated_at' => $this->account->updated_at ? $this->account->updated_at->toDateString() : null,
        ];
    }

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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
                'last_login' => $client->last_login,
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
            ];
        }

        return $clients;
    }

    /**
     * @return array
     */
    protected function getProducts()
    {
        // Confusions: assigned_user_id, project_id, vendor_id, price,

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

    /**
     * @return array
     */
    public function getUsers()
    {
        // Missing, notes: device_token, ip, theme_id, oauth_user_token, avatar, signature,

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
            ];
        }

        return $transformed;
    }

    private function getCreditsNotes()
    {
        // Confusions, what do to with: assigned_user_id, project_id, vendor_id,
        // line_items, backup, total_taxes, uses_inclusive_taxes, custom_surcharge1, last_viewed,

        // Questions: recurring_id, will be added when we split invoices by is_recurring?

        $credits = [];

        foreach ($this->account->invoices()->where('amount', '<', '0')->withTrashed()->get() as $credit) {
            $invoices[] = [
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
                'created_at' => $credit->created_at ? $invoice->created_at->toDateString() : null,
                'updated_at' => $credit->updated_at ? $invoice->updated_at->toDateString() : null,
                'deleted_at' => $credit->deleted_at ? $invoice->deleted_at->toDateString() : null,
            ];
        }

        return $credits;
    }

    /**
     * @return array
     */
    protected function getInvoices()
    {
        // Confusions, what do to with: assigned_user_id, project_id, vendor_id,
        // line_items, backup, total_taxes, uses_inclusive_taxes, custom_surcharge1, last_viewed,

        // Questions: recurring_id, will be added when we split invoices by is_recurring?

        $invoices = [];

        foreach ($this->account->invoices()->where('amount', '>=', '0')->withTrashed()->get() as $invoice) {
            $invoices[] = [
                'id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'user_id' => $invoice->user_id,
                'company_id' => $invoice->account_id,
                'status_id' => $invoice->invoice_status_id,
                'design_id' => $invoice->invoice_design_id,
                'number' => $invoice->invoice_number,
                'discount' => $invoice->discount,
                'is_amount_discount' => $invoice->is_amount_discount ?: false,
                'po_number' => $invoice->po_number,
                'date' => $invoice->invoice_date,
                'last_sent_date' => $invoice->last_sent_date,
                'due_date' => $invoice->due_date,
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
            ];
        }

        return $invoices;
    }

    /**
     * @param $items
     * @return array
     */
    public function getInvoiceItems($items)
    {
        $transformed = [];

        // Missing on V1: is_amount_discount, sort_id, line_total

        foreach ($items as $item) {
            $transformed[] = [
                'id' => $item->id,
                'quantity' => $item->qty,
                'cost' => $item->cost,
                'product_key' => $item->product_key,
                'notes' => $item->notes,
                'discount' => $item->discount,
                'tax_name1' => $item->tax_name1,
                'tax_rate1' => $item->tax_rate1,
                'date' => $item->created_at, // needs verification
                'custom_value1' => $item->custom_value1,
                'custom_value2' => $item->custom_value2,
                'line_item_type_id' => $item->invoice_item_type_id, // needs verification
            ];
        }

        return $transformed;
    }

    /**
     * @return array
     */
    public function getQuotes()
    {
        $transformed = [];

        $quotes = Invoice::where('account_id', $this->account->id)
            ->where('invoice_type_id', '=', INVOICE_TYPE_QUOTE)
            ->withTrashed()
            ->get();

        // Notes: assigned_user_id, project_id,
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
                'created_at' => $quote->created_at ? $quote->created_at->toDateString() : null,
                'updated_at' => $quote->updated_at ? $quote->updated_at->toDateString() : null,
                'deleted_at' => $quote->deleted_at ? $quote->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    public function getPayments()
    {
        $transformed = [];

        $payments = Payment::where('account_id', $this->account->id)
            ->withTrashed()
            ->get();

        // 'invoice_id' missing in the v2? 'project_id' from v2 missing in the v1?
        // vendor_id, applied,

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
                //'number' => $payment->routing_number, // @needs verification
                'updated_at' => $payment->updated_at ? $payment->updated_at->toDateString() : null,
                'created_at' => $payment->created_at ? $payment->created_at->toDateString() : null,
                'deleted_at' => $payment->deleted_at ? $payment->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }

    /**
     * @return array
     */
    private function getCredits()
    {
        $credits = Credit::where('account_id', $this->account->id)->where('balance', '>', '0')->whereIsDeleted(false)
            ->withTrashed()
            ->get();

        $transformed = [];

        // Notes: public_id & a lot of missing fields that are available only on v2

        foreach ($credits as $credit) {
            $transformed[] = [
                'client_id' => $credit->client_id,
                'user_id' => $credit->user_id,
                'company_id' => $credit->account_id,
                'is_deleted' => $credit->is_deleted,
                'amount' => $credit->balance,
                'applied' => 0,
                'refunded' => 0,
                'date' => $credit->date, // needs verification
                //'private_notes' => $credit->private_notes,
                'created_at' => $credit->created_at ? $credit->created_at->toDateString() : null,
                'updated_at' => $credit->updated_at ? $credit->updated_at->toDateString() : null,
                'deleted_at' => $credit->deleted_at ? $credit->deleted_at->toDateString() : null,
            ];
        }

        return $transformed;
    }
}
