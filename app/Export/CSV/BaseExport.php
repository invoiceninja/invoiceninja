<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Utils\Number;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\GatewayType;
use App\Models\Payment;
use League\Fractal\Manager;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\Transformers\ClientTransformer;
use App\Transformers\PaymentTransformer;
use Illuminate\Database\Eloquent\Builder;
use League\Fractal\Serializer\ArraySerializer;

class BaseExport
{
    use MakesHash;

    public array $input;

    public string $date_key = '';

    public array $entity_keys = [];

    public string $start_date = '';

    public string $end_date = '';

    public string $client_description = 'All Clients';

    public array $forced_keys = [];

    protected array $client_report_keys = [
        "name" => "client.name",
        "user" => "client.user_id",
        "balance" => "client.balance",
        "paid_to_date" => "client.paid_to_date",
        "currency" => "client.currency_id",
        "website" => "client.website",
        "private_notes" => "client.private_notes",
        "industry" => "client.industry_id",
        "size" => "client.size_id",
        "work_phone" => "client.phone",
        "address1" => "client.address1",
        "address2" => "client.address2",
        "city" => "client.city",
        "state" => "client.state",
        "postal_code" => "client.postal_code",
        "country" => "client.country_id",
        "custom_value4" => "contact.custom_value4",
        "shipping_address1" => "client.shipping_address1",
        "shipping_address2" => "client.shipping_address2",
        "shipping_city" => "client.shipping_city",
        "shipping_state" => "client.shipping_state",
        "shipping_postal_code" => "client.shipping_postal_code",
        "shipping_country" => "client.shipping_country_id",
        "payment_terms" => "client.payment_terms",
        "vat_number" => "client.vat_number",
        "id_number" => "client.id_number",
        "public_notes" => "client.public_notes",
        "phone" => "contact.phone",
        "first_name" => "contact.first_name",
        "last_name" => "contact.last_name",
        "email" => "contact.email",
    ];

    protected array $invoice_report_keys = [
        "invoice_number" => "invoice.number",
        "amount" => "invoice.amount",
        "balance" => "invoice.balance",
        "paid_to_date" => "invoice.paid_to_date",
        "po_number" => "invoice.po_number",
        "date" => "invoice.date",
        "due_date" => "invoice.due_date",
        "terms" => "invoice.terms",
        "footer" => "invoice.footer",
        "status" => "invoice.status",
        "public_notes" => "invoice.public_notes",
        "private_notes" => "invoice.private_notes",
        "uses_inclusive_taxes" => "invoice.uses_inclusive_taxes",
        "is_amount_discount" => "invoice.is_amount_discount",
        "partial" => "invoice.partial",
        "partial_due_date" => "invoice.partial_due_date",
        "surcharge1" => "invoice.custom_surcharge1",
        "surcharge2" => "invoice.custom_surcharge2",
        "surcharge3" => "invoice.custom_surcharge3",
        "surcharge4" => "invoice.custom_surcharge4",
        "exchange_rate" => "invoice.exchange_rate",
        "tax_amount" => "invoice.total_taxes",
        "assigned_user" => "invoice.assigned_user_id",
        "user" => "invoice.user_id",
    ];

    protected array $item_report_keys = [
        "quantity" => "item.quantity",
        "cost" => "item.cost",
        "product_key" => "item.product_key",
        "notes" => "item.notes",
        "item_tax1" => "item.tax_name1",
        "item_tax_rate1" => "item.tax_rate1",
        "item_tax2" => "item.tax_name2",
        "item_tax_rate2" => "item.tax_rate2",
        "item_tax3" => "item.tax_name3",
        "item_tax_rate3" => "item.tax_rate3",
        "custom_value1" => "item.custom_value1",
        "custom_value2" => "item.custom_value2",
        "custom_value3" => "item.custom_value3",
        "custom_value4" => "item.custom_value4",
        "discount" => "item.discount",
        "type" => "item.type_id",
        "tax_category" => "item.tax_id",
    ];

    protected array $quote_report_keys = [
        "quote_number" => "quote.number",
        "amount" => "quote.amount",
        "balance" => "quote.balance",
        "paid_to_date" => "quote.paid_to_date",
        "po_number" => "quote.po_number",
        "date" => "quote.date",
        "due_date" => "quote.due_date",
        "terms" => "quote.terms",
        "footer" => "quote.footer",
        "status" => "quote.status",
        "public_notes" => "quote.public_notes",
        "private_notes" => "quote.private_notes",
        "uses_inclusive_taxes" => "quote.uses_inclusive_taxes",
        "is_amount_discount" => "quote.is_amount_discount",
        "partial" => "quote.partial",
        "partial_due_date" => "quote.partial_due_date",
        "surcharge1" => "quote.custom_surcharge1",
        "surcharge2" => "quote.custom_surcharge2",
        "surcharge3" => "quote.custom_surcharge3",
        "surcharge4" => "quote.custom_surcharge4",
        "exchange_rate" => "quote.exchange_rate",
        "tax_amount" => "quote.total_taxes",
        "assigned_user" => "quote.assigned_user_id",
        "user" => "quote.user_id",
    ];

    protected array $credit_report_keys = [
        "credit_number" => "credit.number",
        "amount" => "credit.amount",
        "balance" => "credit.balance",
        "paid_to_date" => "credit.paid_to_date",
        "po_number" => "credit.po_number",
        "date" => "credit.date",
        "due_date" => "credit.due_date",
        "terms" => "credit.terms",
        "footer" => "credit.footer",
        "status" => "credit.status",
        "public_notes" => "credit.public_notes",
        "private_notes" => "credit.private_notes",
        "uses_inclusive_taxes" => "credit.uses_inclusive_taxes",
        "is_amount_discount" => "credit.is_amount_discount",
        "partial" => "credit.partial",
        "partial_due_date" => "credit.partial_due_date",
        "surcharge1" => "credit.custom_surcharge1",
        "surcharge2" => "credit.custom_surcharge2",
        "surcharge3" => "credit.custom_surcharge3",
        "surcharge4" => "credit.custom_surcharge4",
        "exchange_rate" => "credit.exchange_rate",
        "tax_amount" => "credit.total_taxes",
        "assigned_user" => "credit.assigned_user_id",
        "user" => "credit.user_id",
  ];

    protected array $payment_report_keys = [
        "date" => "payment.date",
        "amount" => "payment.amount",
        "refunded" => "payment.refunded",
        "applied" => "payment.applied",
        "transaction_reference" => "payment.transaction_reference",
        "currency" => "payment.currency",
        "exchange_rate" => "payment.exchange_rate",
        "number" => "payment.number",
        "method" => "payment.method",
        "status" => "payment.status",
        "private_notes" => "payment.private_notes",
        "custom_value1" => "payment.custom_value1",
        "custom_value2" => "payment.custom_value2",
        "custom_value3" => "payment.custom_value3",
        "custom_value4" => "payment.custom_value4",
        "user" => "payment.user_id",
        "assigned_user" => "payment.assigned_user_id",
  ];

    protected function filterByClients($query)
    {
        if (isset($this->input['client_id']) && $this->input['client_id'] != 'all') {
            $client = Client::withTrashed()->find($this->input['client_id']);
            $this->client_description = $client->present()->name;
            return $query->where('client_id', $this->input['client_id']);
        }
        elseif(isset($this->input['clients']) && count($this->input['clients']) > 0) {

            $this->client_description = 'Multiple Clients';
            return $query->whereIn('client_id', $this->input['clients']);
        }
        return $query;
    }

    protected function resolveKey($key, $entity, $transformer) :string
    {
        $parts = explode(".", $key);

        if(!is_array($parts) || count($parts) < 2)
            return '';

        match($parts[0]) {
            'contact' => $value = $this->resolveClientContactKey($parts[1], $entity, $transformer),
            'client' => $value = $this->resolveClientKey($parts[1], $entity, $transformer),
            'invoice' => $value = $this->resolveInvoiceKey($parts[1], $entity, $transformer),
            'payment' => $value = $this->resolvePaymentKey($parts[1], $entity, $transformer),
            default => $value = ''
        };
        
        return $value;
    }

    private function resolveClientContactKey($column, $entity, $transformer)
    {

        $primary_contact = $entity->client->primary_contact()->first() ?? $entity->client->contacts()->first();

        return $primary_contact?->{$column} ?? '';

    }

    private function resolveClientKey($column, $entity, $transformer)
    {
        $transformed_client = $transformer->includeClient($entity);

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $transformed_client = $manager->createData($transformed_client)->toArray();

        if($column == 'name')
            return $transformed_client['display_name'];
        
        if($column == 'user_id')
            return $entity->client->user->present()->name();

        if($column == 'country_id')
            return $entity->client->country ? ctrans("texts.country_{$entity->client->country->name}") : '';
        
        if($column == 'shipping_country_id')
            return $entity->client->shipping_country ? ctrans("texts.country_{$entity->client->shipping_country->name}") : '';
        
        if($column == 'size_id')
            return $entity->client->size?->name ?? '';

        if($column == 'industry_id')
            return $entity->client->industry?->name ?? '';

        if ($column == 'currency_id') {
            return $entity->client->currency() ? $entity->client->currency()->code : $entity->company->currency()->code;
        }

        if($column == 'client.payment_terms') {
            return $entity->client->getSetting('payment_terms');
        }

        if(array_key_exists($column, $transformed_client))
            return $transformed_client[$column];

        nlog("export: Could not resolve client key: {$column}");

        return '';

    }

    private function resolveInvoiceKey($column, $entity, $transformer)
    {
        nlog("searching for {$column}");

        if($transformer instanceof PaymentTransformer) {
            $transformed_invoices = $transformer->includeInvoices($entity);

            $manager = new Manager();
            $manager->setSerializer(new ArraySerializer());
            $transformed_invoices = $manager->createData($transformed_invoices)->toArray();

            if(!isset($transformed_invoices['App\\Models\\Invoice']))
                return '';
           
            $transformed_invoices = $transformed_invoices['App\\Models\\Invoice'];

            if(count($transformed_invoices) == 1 && array_key_exists($column, $transformed_invoices[0]))
                return $transformed_invoices[0][$column];

            if(count($transformed_invoices) > 1 && array_key_exists($column, $transformed_invoices[0]))
                return implode(', ', array_column($transformed_invoices, $column));

            return "";

        }

        $transformed_invoice = $transformer->transform($entity);

        if($column == 'status')
            return $entity->stringStatus($entity->status_id);
    
        return '';
    }

    private function resolvePaymentKey($column, $entity, $transformer)
    {

        if($entity instanceof Payment){

            $transformed_payment = $transformer->transform($entity);

            if(array_key_exists($column, $transformed_payment)) {
                return $transformed_payment[$column];
            } elseif (array_key_exists(str_replace("payment.", "", $column), $transformed_payment)) {
                return $transformed_payment[$column];
            }

            nlog("export: Could not resolve payment key: {$column}");

            return '';

        }

        if($column == 'amount')
            return $entity->payments()->exists() ? Number::formatMoney($entity->payments()->sum('paymentables.amount'), $entity->company) : ctrans('texts.unpaid');

        if($column == 'refunded') {
            return $entity->payments()->exists() ? Number::formatMoney($entity->payments()->sum('paymentables.refunded'), $entity->company) : 0;
        }

        if($column == 'applied') {
            $refunded = $entity->payments()->sum('paymentables.refunded');
            $amount = $entity->payments()->sum('paymentables.amount');

            return $entity->payments()->exists() ? Number::formatMoney(($amount - $refunded), $entity->company) : 0;
        }

        $payment = $entity->payments()->first();

        if(!$payment)
            return '';

        if($column == 'method')
            return $payment->translatedType();

        if($column == 'currency')
            return $payment?->currency?->code ?? '';

        $payment_transformer = new PaymentTransformer();
        $transformed_payment = $payment_transformer->transform($payment);

        if($column == 'status'){
            return $payment->stringStatus($transformed_payment['status_id']);
        }

        if(array_key_exists($column, $transformed_payment))
            return $transformed_payment[$column];

        return '';

    }

    protected function addInvoiceStatusFilter($query, $status): Builder
    {

        $status_parameters = explode(',', $status);
        

        if(in_array('all', $status_parameters))
            return $query;

        $query->where(function ($nested) use ($status_parameters) {

            $invoice_filters = [];

            if (in_array('draft', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_DRAFT;
            }

            if (in_array('sent', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_SENT;
            }

            if (in_array('paid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_PAID;
            }

            if (in_array('unpaid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_SENT;
                $invoice_filters[] = Invoice::STATUS_PARTIAL;
            }

            if (count($invoice_filters) > 0) {
                $nested->whereIn('status_id', $invoice_filters);
            }
                                
            if (in_array('overdue', $status_parameters)) {
                $nested->orWhereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                ->where('due_date', '<', Carbon::now())
                                ->orWhere('partial_due_date', '<', Carbon::now());
            }

            if(in_array('viewed', $status_parameters)){
                
                $nested->whereHas('invitations', function ($q){
                    $q->whereNotNull('viewed_date')->whereNotNull('deleted_at');
                });

            }
                
            
        });

        return $query;
    }

    protected function addDateRange($query)
    {
        $date_range = $this->input['date_range'];

        if (array_key_exists('date_key', $this->input) && strlen($this->input['date_key']) > 1) {
            $this->date_key = $this->input['date_key'];
        }

        try {
            $custom_start_date = Carbon::parse($this->input['start_date']);
            $custom_end_date = Carbon::parse($this->input['end_date']);
        } catch (\Exception $e) {
            $custom_start_date = now()->startOfYear();
            $custom_end_date = now();
        }

        switch ($date_range) {
            case 'all':
                $this->start_date = 'All available data';
                $this->end_date = 'All available data';
                return $query;
            case 'last7':
                $this->start_date = now()->subDays(7)->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->subDays(7), now()])->orderBy($this->date_key, 'ASC');
            case 'last30':
                $this->start_date = now()->subDays(30)->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->subDays(30), now()])->orderBy($this->date_key, 'ASC');
            case 'this_month':
                $this->start_date = now()->startOfMonth()->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfMonth(), now()])->orderBy($this->date_key, 'ASC');
            case 'last_month':
                $this->start_date = now()->startOfMonth()->subMonth()->format('Y-m-d');
                $this->end_date = now()->startOfMonth()->subMonth()->endOfMonth()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfMonth()->subMonth(), now()->startOfMonth()->subMonth()->endOfMonth()])->orderBy($this->date_key, 'ASC');
            case 'this_quarter':
                $this->start_date = (new \Carbon\Carbon('-3 months'))->firstOfQuarter()->format('Y-m-d');
                $this->end_date = (new \Carbon\Carbon('-3 months'))->lastOfQuarter()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-3 months'))->firstOfQuarter(), (new \Carbon\Carbon('-3 months'))->lastOfQuarter()])->orderBy($this->date_key, 'ASC');
            case 'last_quarter':
                $this->start_date = (new \Carbon\Carbon('-6 months'))->firstOfQuarter()->format('Y-m-d');
                $this->end_date = (new \Carbon\Carbon('-6 months'))->lastOfQuarter()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-6 months'))->firstOfQuarter(), (new \Carbon\Carbon('-6 months'))->lastOfQuarter()])->orderBy($this->date_key, 'ASC');
            case 'last365_days':
                $this->start_date = now()->startOfDay()->subDays(365)->format('Y-m-d');
                $this->end_date = now()->startOfDay()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->subDays(365), now()])->orderBy($this->date_key, 'ASC');
            case 'this_year':
                $this->start_date = now()->startOfYear()->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfYear(), now()])->orderBy($this->date_key, 'ASC');
            case 'custom':
                $this->start_date = $custom_start_date->format('Y-m-d');
                $this->end_date = $custom_end_date->format('Y-m-d');
                return $query->whereBetween($this->date_key, [$custom_start_date, $custom_end_date])->orderBy($this->date_key, 'ASC');
            default:
                $this->start_date = now()->startOfYear()->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfYear(), now()])->orderBy($this->date_key, 'ASC');
        }
    }

    public function buildHeader() :array
    {
        $header = [];

        foreach (array_merge($this->input['report_keys'], $this->forced_keys) as $value) {

            $key = array_search($value, $this->entity_keys);
            
            $prefix = '';

            if(!$key) {
                $prefix = stripos($value, 'client.') !== false ? ctrans('texts.client') : ctrans('texts.contact');
                $key = array_search($value, $this->client_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.invoice');
                $key = array_search($value, $this->invoice_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.payment');
                $key = array_search($value, $this->payment_report_keys);
            }


            if(!$key) {
                $prefix = ctrans('texts.quote');
                $key = array_search($value, $this->quote_report_keys);
            }
            
            if(!$key) {
                $prefix = ctrans('texts.credit');
                $key = array_search($value, $this->credit_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.item');
                $key = array_search($value, $this->item_report_keys);
            }

            if(!$key) {
                $prefix = '';
            }

            $key = str_replace('item.', '', $key);
            $key = str_replace('invoice.', '', $key);
            $key = str_replace('client.', '', $key);
            $key = str_replace('contact.', '', $key);
            $key = str_replace('payment.', '', $key);

            $header[] = "{$prefix} " . ctrans("texts.{$key}");
        }

        return $header;
    }
}
