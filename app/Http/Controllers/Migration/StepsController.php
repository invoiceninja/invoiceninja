<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Libraries\Utils;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleDownload(Request $request)
    {
        $this->account = Auth::user()->account;

        $date = date('Y-m-d');
        $accountKey = $this->account->account_key;

        $output = fopen('php://output', 'w') or Utils::fatalError();

        $fileName = "{$accountKey}-{$date}-invoiceninja";

        // header('Content-Type:application/json');
        // header("Content-Disposition:attachment;filename={$fileName}.json");

        $data = [
            'company' => $this->getCompany(),
            'users' => $this->getUsers(),
            'clients' => $this->getClients(),
            'invoices' => $this->getInvoices(),
        ];

        // @TODO: Replace with .env variable (where to store local migrations - disk()).
        Storage::put("migrations/{$fileName}/migration.json", json_encode($data));

        $logo = public_path(sprintf(
            'logo%s%s', DIRECTORY_SEPARATOR, $this->account->logo
        ));

        if(Storage::exists('file.jpg')) {
            Storage::copy(public_path("logo/{$this->account->logo}"), app_path("migrations/{$fileName}/{$this->account->logo}"));
        }

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
            [
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
                'created_at' => $this->account->created_at,
                'updated_at' => $this->account->updated_at,
            ]
        ];
    }

    /**
     * Export clients and map to the v2 fields.
     *
     * @return array
     */
    protected function getClients()
    {
        $clients = [];

        foreach ($this->account->clients()->withTrashed()->get() as $client) {
            $clients[] = [
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
     * Export needed users and map to v2 fields.
     *
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
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'deleted_at' => $user->deleted_at,
            ];
        }

        return $transformed;
    }

    /**
     * Export invoices and mappings for the v2.
     */
    protected function getInvoices()
    {
        // Confusions, what do to with: assigned_user_id, project_id, vendor_id,
        // line_items, backup, total_taxes, uses_inclusive_taxes, custom_surcharge1, last_viewed,

        // Questions: recurring_id, will be added when we split invoices by is_recurring?

        $invoices = [];

        foreach ($this->account->invoices()->withTrashed()->get() as $invoice) {
            $invoices[] = [
                'client_id' => $invoice->client_id,
                'user_id' => $invoice->user_id,
                'company_id' => $invoice->account_id,
                'status_id' => $invoice->invoice_status_id,
                'design_id' => $invoice->invoice_design_id,
                'number' => $invoice->invoice_number,
                'discount' => $invoice->discount,
                'is_amount_discount' => $invoice->is_amount_discount,
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
                'next_send_date' => $invoice->due_date, // needs confirm,
                'amount' => $invoice->amount,
                'balance' => $invoice->balance,
                'partial' => $invoice->partial,
                'partial_due_date' => $invoice->partial_due_date,
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
                'deleted_at' => $invoice->deleted_at,
            ];
        }

        return $invoices;
    }
}
