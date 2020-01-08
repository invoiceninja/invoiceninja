<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Libraries\Utils;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

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
            'company' => $this->exportCompany(),
            'users' => $this->exportUsers(),
            'clients' => $this->exportClients(),
        ];

        return response()->json($data);
    }

    /**
     * Export company and map to the v2 fields.
     *
     * @return array
     */
    protected function exportCompany()
    {
        // Notes: show_product_details, show_product_cost
        // What to do with: enabled_tax_rates, enable_product_costs, enable_product_quantity, portal_mode, portal_domain,

        return [
            'account_id' => Auth::id(),
            'industry_id' => $this->account->industry_id,
            'ip' => $this->account->ip,
            'company_key' => $this->account->account_key,
            'logo' => $this->account->logo,
            'convert_products' => $this->account->convert_products,
            'fill_products' => $this->account->fill_products,
            'update_products' => $this->account->update_products,
            'show_product_details' => $this->account->show_product_notes, // needs confirmation
            'custom_surcharge_taxes1' => $this->account->custom_invoice_taxes1, // needs confirmation
            'custom_surcharge_taxes2' => $this->account->custom_invoice_taxes2, // needs confirmation
            'enable_invoice_quantity' => !$this->account->hide_quantity, // needs confirmation
            'subdomain' => $this->account->subdomain,
            'size_id' => $this->account->size_id,
            'enable_modules' => $this->account->enabled_modules, // possible typo in v2? enable vs enabled,
            'custom_fields' => $this->account->custom_fields,
            'created_at' => $this->account->created_at,
            'updated_at' => $this->account->updated_at,
        ];
    }

    /**
     * Export clients and map to the v2 fields.
     *
     * @return array
     */
    protected function exportClients()
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
    public function exportUsers()
    {
        return [];
    }
}
