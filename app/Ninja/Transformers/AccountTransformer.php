<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\AccountToken;
use App\Models\Contact;
use App\Models\Product;
use App\Models\TaxRate;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class AccountTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'users',
        'products',
        'taxRates',
    ];

    protected $availableIncludes = [
        'clients',
        'invoices',
        'payments',
    ];

    public function includeUsers(Account $account)
    {
        $transformer = new UserTransformer($account, $this->serializer);
        return $this->includeCollection($account->users, $transformer, 'users');
    }

    public function includeClients(Account $account)
    {
        $transformer = new ClientTransformer($account, $this->serializer);
        return $this->includeCollection($account->clients, $transformer, 'clients');
    }

    public function includeInvoices(Account $account)
    {
        $transformer = new InvoiceTransformer($account, $this->serializer);
        return $this->includeCollection($account->invoices, $transformer, 'invoices');
    }

    public function includeProducts(Account $account)
    {
        $transformer = new ProductTransformer($account, $this->serializer);
        return $this->includeCollection($account->products, $transformer, 'products');
    }

    public function includeTaxRates(Account $account)
    {
        $transformer = new TaxRateTransformer($account, $this->serializer);
        return $this->includeCollection($account->tax_rates, $transformer, 'taxRates');
    }

    public function includePayments(Account $account)
    {
        $transformer = new PaymentTransformer($account, $this->serializer);
        return $this->includeCollection($account->payments, $transformer, 'payments');
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
            'custom_value2' => $account->custom_value2
        ];
    }
}