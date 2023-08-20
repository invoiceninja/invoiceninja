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
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use League\Fractal\Manager;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\Transformers\TaskTransformer;
use App\Transformers\PaymentTransformer;
use Illuminate\Database\Eloquent\Builder;
use League\Fractal\Serializer\ArraySerializer;

class BaseExport
{
    use MakesHash;

    public Company $company;
    
    public array $input;

    public string $date_key = '';

    public array $entity_keys = [];

    public string $start_date = '';

    public string $end_date = '';

    public string $client_description = 'All Clients';

    public array $forced_keys = [];

    protected array $vendor_report_keys = [
        'address1' => 'vendor.address1',
        'address2' => 'vendor.address2',
        'city' => 'vendor.city',
        'country' => 'vendor.country_id',
        'custom_value1' => 'vendor.custom_value1',
        'custom_value2' => 'vendor.custom_value2',
        'custom_value3' => 'vendor.custom_value3',
        'custom_value4' => 'vendor.custom_value4',
        'id_number' => 'vendor.id_number',
        'name' => 'vendor.name',
        'number' => 'vendor.number',
        'client_phone' => 'vendor.phone',
        'postal_code' => 'vendor.postal_code',
        'private_notes' => 'vendor.private_notes',
        'public_notes' => 'vendor.public_notes',
        'state' => 'vendor.state',
        'vat_number' => 'vendor.vat_number',
        'website' => 'vendor.website',
        'currency' => 'vendor.currency',
        'first_name' => 'vendor_contact.first_name',
        'last_name' => 'vendor_contact.last_name',
        'contact_phone' => 'vendor_contact.phone',
        'contact_custom_value1' => 'vendor_contact.custom_value1',
        'contact_custom_value2' => 'vendor_contact.custom_value2',
        'contact_custom_value3' => 'vendor_contact.custom_value3',
        'contact_custom_value4' => 'vendor_contact.custom_value4',
        'email' => 'vendor_contact.email',
        'status' => 'vendor.status',
    ];

    protected array $client_report_keys = [
        "name" => "client.name",
        "user" => "client.user",
        "assigned_user" => "client.assigned_user",
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
        'custom_value1' => 'client.custom_value1',
        'custom_value2' => 'client.custom_value2',
        'custom_value3' => 'client.custom_value3',
        'custom_value4' => 'client.custom_value4',
        "contact_custom_value1" => "contact.custom_value1",
        "contact_custom_value2" => "contact.custom_value2",
        "contact_custom_value3" => "contact.custom_value3",
        "contact_custom_value4" => "contact.custom_value4",

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

    protected array $recurring_invoice_report_keys = [    
        "invoice_number" => "recurring_invoice.number",
        "amount" => "recurring_invoice.amount",
        "balance" => "recurring_invoice.balance",
        "paid_to_date" => "recurring_invoice.paid_to_date",
        "po_number" => "recurring_invoice.po_number",
        "date" => "recurring_invoice.date",
        "due_date" => "recurring_invoice.due_date",
        "terms" => "recurring_invoice.terms",
        "footer" => "recurring_invoice.footer",
        "status" => "recurring_invoice.status",
        "public_notes" => "recurring_invoice.public_notes",
        "private_notes" => "recurring_invoice.private_notes",
        "uses_inclusive_taxes" => "recurring_invoice.uses_inclusive_taxes",
        "is_amount_discount" => "recurring_invoice.is_amount_discount",
        "partial" => "recurring_invoice.partial",
        "partial_due_date" => "recurring_invoice.partial_due_date",
        "surcharge1" => "recurring_invoice.custom_surcharge1",
        "surcharge2" => "recurring_invoice.custom_surcharge2",
        "surcharge3" => "recurring_invoice.custom_surcharge3",
        "surcharge4" => "recurring_invoice.custom_surcharge4",
        "exchange_rate" => "recurring_invoice.exchange_rate",
        "tax_amount" => "recurring_invoice.total_taxes",
        "assigned_user" => "recurring_invoice.assigned_user_id",
        "user" => "recurring_invoice.user_id",
        "frequency_id" => "recurring_invoice.frequency_id",
        "next_send_date" => "recurring_invoice.next_send_date"
    ];

    protected array $purchase_order_report_keys = [
        'amount' => 'purchase_order.amount',
        'balance' => 'purchase_order.balance',
        'vendor' => 'purchase_order.vendor_id',
        // 'custom_surcharge1' => 'purchase_order.custom_surcharge1',
        // 'custom_surcharge2' => 'purchase_order.custom_surcharge2',
        // 'custom_surcharge3' => 'purchase_order.custom_surcharge3',
        // 'custom_surcharge4' => 'purchase_order.custom_surcharge4',
        'custom_value1' => 'purchase_order.custom_value1',
        'custom_value2' => 'purchase_order.custom_value2',
        'custom_value3' => 'purchase_order.custom_value3',
        'custom_value4' => 'purchase_order.custom_value4',
        'date' => 'purchase_order.date',
        'discount' => 'purchase_order.discount',
        'due_date' => 'purchase_order.due_date',
        'exchange_rate' => 'purchase_order.exchange_rate',
        'footer' => 'purchase_order.footer',
        'number' => 'purchase_order.number',
        'paid_to_date' => 'purchase_order.paid_to_date',
        'partial' => 'purchase_order.partial',
        'partial_due_date' => 'purchase_order.partial_due_date',
        'po_number' => 'purchase_order.po_number',
        'private_notes' => 'purchase_order.private_notes',
        'public_notes' => 'purchase_order.public_notes',
        'status' => 'purchase_order.status_id',
        'tax_name1' => 'purchase_order.tax_name1',
        'tax_name2' => 'purchase_order.tax_name2',
        'tax_name3' => 'purchase_order.tax_name3',
        'tax_rate1' => 'purchase_order.tax_rate1',
        'tax_rate2' => 'purchase_order.tax_rate2',
        'tax_rate3' => 'purchase_order.tax_rate3',
        'terms' => 'purchase_order.terms',
        'total_taxes' => 'purchase_order.total_taxes',
        'currency_id' => 'purchase_order.currency_id',
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
        'custom_value1' => 'quote.custom_value1',
        'custom_value2' => 'quote.custom_value2',
        'custom_value3' => 'quote.custom_value3',
        'custom_value4' => 'quote.custom_value4',
        "number" => "quote.number",
        "amount" => "quote.amount",
        "balance" => "quote.balance",
        "paid_to_date" => "quote.paid_to_date",
        "po_number" => "quote.po_number",
        "date" => "quote.date",
        "valid_until" => "quote.due_date",
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

    protected array $expense_report_keys = [
        'amount' => 'expense.amount',
        'category' => 'expense.category_id',
        'client' => 'expense.client_id',
        'custom_value1' => 'expense.custom_value1',
        'custom_value2' => 'expense.custom_value2',
        'custom_value3' => 'expense.custom_value3',
        'custom_value4' => 'expense.custom_value4',
        'currency' => 'expense.currency_id',
        'date' => 'expense.date',
        'exchange_rate' => 'expense.exchange_rate',
        'converted_amount' => 'expense.foreign_amount',
        'invoice_currency_id' => 'expense.invoice_currency_id',
        'payment_date' => 'expense.payment_date',
        'number' => 'expense.number',
        'payment_type_id' => 'expense.payment_type_id',
        'private_notes' => 'expense.private_notes',
        'project' => 'expense.project_id',
        'public_notes' => 'expense.public_notes',
        'tax_amount1' => 'expense.tax_amount1',
        'tax_amount2' => 'expense.tax_amount2',
        'tax_amount3' => 'expense.tax_amount3',
        'tax_name1' => 'expense.tax_name1',
        'tax_name2' => 'expense.tax_name2',
        'tax_name3' => 'expense.tax_name3',
        'tax_rate1' => 'expense.tax_rate1',
        'tax_rate2' => 'expense.tax_rate2',
        'tax_rate3' => 'expense.tax_rate3',
        'transaction_reference' => 'expense.transaction_reference',
        'vendor' => 'expense.vendor_id',
        'invoice' => 'expense.invoice_id',
        'user' => 'expense.user',
        'assigned_user' => 'expense.assigned_user',
    ];

    protected array $task_report_keys = [
        'start_date' => 'task.start_date',
        'end_date' => 'task.end_date',
        'duration' => 'task.duration',
        'rate' => 'task.rate',
        'number' => 'task.number',
        'description' => 'task.description',
        'custom_value1' => 'task.custom_value1',
        'custom_value2' => 'task.custom_value2',
        'custom_value3' => 'task.custom_value3',
        'custom_value4' => 'task.custom_value4',
        'status' => 'task.status_id',
        'project' => 'task.project_id',
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

        $value = '';

        match($parts[0]) {
            'contact' => $value = $this->resolveClientContactKey($parts[1], $entity, $transformer),
            'client' => $value = $this->resolveClientKey($parts[1], $entity, $transformer),
            'expense' => $value = $this->resolveExpenseKey($parts[1], $entity, $transformer),
            'vendor' => $value = $this->resolveVendorKey($parts[1], $entity, $transformer),
            'vendor_contact' => $value = $this->resolveVendorContactKey($parts[1], $entity, $transformer),
            'invoice' => $value = $this->resolveInvoiceKey($parts[1], $entity, $transformer),
            'recurring_invoice' => $value = $this->resolveInvoiceKey($parts[1], $entity, $transformer),
            'quote' => $value = $this->resolveQuoteKey($parts[1], $entity, $transformer),
            'purchase_order' => $value = $this->resolvePurchaseOrderKey($parts[1], $entity, $transformer),
            'payment' => $value = $this->resolvePaymentKey($parts[1], $entity, $transformer),
            'task' => $value = $this->resolveTaskKey($parts[1], $entity, $transformer),
            default => $value = '',
        };
        
        return $value;
    }

    private function resolveClientContactKey($column, $entity, $transformer)
    {

        if(!$entity->client) {
            return "";
        }

        $primary_contact = $entity->client->primary_contact()->first() ?? $entity->client->contacts()->first();

        return $primary_contact ? $primary_contact?->{$column} ?? '' : '';

    }

    private function resolveVendorContactKey($column, $entity, $transformer)
    {
        if(!$entity->vendor)
            return "";

        $primary_contact = $entity->vendor->primary_contact()->first() ?? $entity->vendor->contacts()->first();

        return $primary_contact ? $primary_contact?->{$column} ?? '' : '';

    }


    private function resolveExpenseKey($column, $entity, $transformer)
    {
     
        if($column == 'user' && $entity?->expense?->user)
            return $entity->expense->user->present()->name() ?? ' ';

        if($column == 'assigned_user' && $entity?->expense?->assigned_user) 
            return $entity->expense->assigned_user->present()->name() ?? ' ';

        if($column == 'category' && $entity->expense) {
            return $entity->expense->category?->name ?? ' ';
        }

        if($entity instanceof Expense)
            return '';

        $transformed_entity = $transformer->includeExpense($entity);

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $transformed_entity = $manager->createData($transformed_entity)->toArray();

        if(array_key_exists($column, $transformed_entity)) 
            return $transformed_entity[$column];    

        if(property_exists($entity, $column))
            return $entity?->{$column} ?? '';

        nlog("export: Could not resolve expense key: {$column}");

        return '';

    }

    private function resolveTaskKey($column, $entity, $transformer)
    {
        // nlog("searching for {$column}");

        $transformed_entity = $transformer->transform($entity);

        if(array_key_exists($column, $transformed_entity)) {
            return $transformed_entity[$column];
        }

        return '';

    }



    private function resolveVendorKey($column, $entity, $transformer)
    {

        if(!$entity->vendor)
            return '';

        $transformed_entity = $transformer->includeVendor($entity);

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $transformed_entity = $manager->createData($transformed_entity)->toArray();

        if($column == 'name')
            return $entity->vendor->present()->name() ?: '';
        
        if($column == 'user_id')
            return $entity->vendor->user->present()->name()  ?: '';

        if($column == 'country_id')
            return $entity->vendor->country ? ctrans("texts.country_{$entity->vendor->country->name}") : '';

        if ($column == 'currency_id') {
            return $entity->vendor->currency() ? $entity->vendor->currency()->code : $entity->company->currency()->code;
        }

        if($column == 'status')
            return $entity->stringStatus($entity->status_id) ?: '';

        if(array_key_exists($column, $transformed_entity))
            return $transformed_entity[$column];

        // nlog("export: Could not resolve vendor key: {$column}");

        return '';

    }


    private function resolveClientKey($column, $entity, $transformer)
    {

        if(!$entity->client)
            return '';

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

        // nlog("export: Could not resolve client key: {$column}");

        return '';

    }

    private function resolvePurchaseOrderKey($column, $entity, $transformer)
    {
        // nlog("searching for {$column}");

        $transformed_entity = $transformer->transform($entity);

        if($column == 'status')
            return $entity->stringStatus($entity->status_id);
    
        return '';
    }

    private function resolveQuoteKey($column, $entity, $transformer)
    {
        // nlog("searching for {$column}");

        $transformed_entity = $transformer->transform($entity);

        if(array_key_exists($column, $transformed_entity)) {
            return $transformed_entity[$column];
        }

        return '';

    }

    private function resolveInvoiceKey($column, $entity, $transformer)
    {
        // nlog("searching for {$column}");
        $transformed_invoice = false;

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

        if($transformer instanceof TaskTransformer) {
            $transformed_invoice = $transformer->includeInvoice($entity);

            if(!$transformed_invoice)
                return '';

            $manager = new Manager();
            $manager->setSerializer(new ArraySerializer());
            $transformed_invoice = $manager->createData($transformed_invoice)->toArray();

        }
        
        if($transformed_invoice && array_key_exists($column, $transformed_invoice)) {
            return $transformed_invoice[$column];
        } elseif ($transformed_invoice && array_key_exists(str_replace("invoice.", "", $column), $transformed_invoice)) {
            return $transformed_invoice[$column];
        }
    
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

            // nlog("export: Could not resolve payment key: {$column}");

            return '';

        }

        if($column == 'amount')
            return $entity->payments()->exists() ? $entity->payments()->withoutTrashed()->sum('paymentables.amount') : ctrans('texts.unpaid');

        if($column == 'refunded') {
            return $entity->payments()->exists() ? $entity->payments()->withoutTrashed()->sum('paymentables.refunded') : '';
        }

        if($column == 'applied') {
            $refunded = $entity->payments()->withoutTrashed()->sum('paymentables.refunded');
            $amount = $entity->payments()->withoutTrashed()->sum('paymentables.amount');

            return $entity->payments()->withoutTrashed()->exists() ? ($amount - $refunded) : '';
        }

        $payment = $entity->payments()->withoutTrashed()->first();

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

                $first_month_of_year = $this->company->getSetting('first_month_of_year') ?? 1;
                $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

                if(now()->lt($fin_year_start))
                    $fin_year_start->subYearNoOverflow();

                $this->start_date = $fin_year_start->format('Y-m-d');
                $this->end_date = $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfYear(), now()])->orderBy($this->date_key, 'ASC');
            case 'last_year':

                $first_month_of_year = $this->company->getSetting('first_month_of_year') ?? 1;
                $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);
                $fin_year_start->subYearNoOverflow();

                if(now()->subYear()->lt($fin_year_start)) 
                    $fin_year_start->subYearNoOverflow();

                $this->start_date = $fin_year_start->format('Y-m-d');
                $this->end_date = $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d');
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

        // nlog($this->input['report_keys']);

        foreach (array_merge($this->input['report_keys'], $this->forced_keys) as $value) {

            $key = array_search($value, $this->entity_keys);
            nlog("{$key} => {$value}");
            $prefix = '';

            if(!$key) {
                $prefix = stripos($value, 'client.') !== false ? ctrans('texts.client')." " : ctrans('texts.contact')." ";
                $key = array_search($value, $this->client_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.invoice')." ";
                $key = array_search($value, $this->invoice_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.recurring_invoice')." ";
                $key = array_search($value, $this->recurring_invoice_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.payment')." ";
                $key = array_search($value, $this->payment_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.quote')." ";
                $key = array_search($value, $this->quote_report_keys);
            }
            
            if(!$key) {
                $prefix = ctrans('texts.credit')." ";
                $key = array_search($value, $this->credit_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.item')." ";
                $key = array_search($value, $this->item_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.expense')." ";
                $key = array_search($value, $this->expense_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.task')." ";
                $key = array_search($value, $this->task_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.vendor')." ";
                $key = array_search($value, $this->vendor_report_keys);
            }

            if(!$key) {
                $prefix = ctrans('texts.purchase_order')." ";
                $key = array_search($value, $this->purchase_order_report_keys);
            }

            if(!$key) {
                $prefix = '';
            }

            $key = str_replace('item.', '', $key);
            $key = str_replace('recurring_invoice.', '', $key);
            $key = str_replace('purchase_order.', '', $key);
            $key = str_replace('invoice.', '', $key);
            $key = str_replace('quote.', '', $key);
            $key = str_replace('credit.', '', $key);
            $key = str_replace('task.', '', $key);
            $key = str_replace('client.', '', $key);
            $key = str_replace('vendor.', '', $key);
            $key = str_replace('contact.', '', $key);
            $key = str_replace('payment.', '', $key);
            $key = str_replace('expense.', '', $key);
// nlog($key);
            if(in_array($key, ['quote1','quote2','quote3','quote4','credit1','credit2','credit3','credit4','purchase_order1','purchase_order2','purchase_order3','purchase_order4']))
            {
                $number = substr($key, -1);
                $header[] = ctrans('texts.item') . " ". ctrans("texts.custom_value{$number}"); 
            }
            else
            {
                $header[] = "{$prefix}" . ctrans("texts.{$key}");
            }
        }

        // nlog($header);

        return $header;
    }
}
