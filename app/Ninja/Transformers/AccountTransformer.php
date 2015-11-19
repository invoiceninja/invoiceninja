<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\AccountToken;
use App\Models\Contact;
use App\Models\Product;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class AccountTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'users',
        'clients',
        'invoices',
        'contacts',
        'products',
    ];

    public function includeUsers(Account $account)
    {
        return $this->collection($account->users, new UserTransformer($account));
    }

    public function includeClients(Account $account)
    {
        return $this->collection($account->clients, new ClientTransformer($account));
    }

    public function includeInvoices(Account $account)
    {
        return $this->collection($account->invoices, new InvoiceTransformer($account));
    }

    public function includeContacts(Account $account)
    {
        return $this->collection($account->contacts, new ContactTransformer($account));
    }

    public function includeProducts(Account $account)
    {
        return $this->collection($account->products, new ProductTransformer($account));
    }

    public function transform(Account $account)
    {
        return [
            'account_key' => $account->account_key,
            'name' => $account->present()->name,
            'currency_id' => (int) $account->currency_id,
            'timezone_id' => (int) $account->timezone_id,
            'date_format_id' => (int) $account->date_format_id,
            'datetime_format_id' => (int) $account->datetime_format_id,
            'updated_at' => $account->updated_at,
            'deleted_at' => $account->deleted_at,
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
            'work_phone' => $account->work_phone,
            'work_email' => $account->work_email,
            'language_id' => (int) $account->language_id,
            'fill_products' => (bool) $account->fill_products,
            'update_products' => (bool) $account->update_products,
            'vat_number' => $account->vat_number
        ];
    }
}