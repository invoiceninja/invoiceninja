<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Jobs;

use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Factory\ClientFactory;
use App\Factory\VendorFactory;
use App\Factory\ExpenseFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Factory\ClientContactFactory;
use App\Factory\VendorContactFactory;
use App\DataMapper\QuickbooksSettings;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Quickbooks\QuickbooksService;
use QuickBooksOnline\API\Data\IPPSalesReceipt;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\VendorTransformer;
use App\Services\Quickbooks\Transformers\ExpenseTransformer;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;
use App\Services\Quickbooks\Transformers\ProductTransformer;

class QuickbooksSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private array $entities = [
        'product' => 'Item',
        'client' => 'Customer',
        'invoice' => 'Invoice',
        // 'quote' => 'Estimate',
        // 'purchase_order' => 'PurchaseOrder',
        // 'payment' => 'Payment',
        'sales' => 'SalesReceipt',
        // 'vendor' => 'Vendor',
        // 'expense' => 'Purchase',
    ];

    private QuickbooksService $qbs;

    private ?array $settings;

    private Company $company;

    public function __construct(public int $company_id, public string $db)
    {
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        MultiDB::setDb($this->db);
     
        $this->company = Company::query()->find($this->company_id);
        $this->qbs = new QuickbooksService($this->company);
        $this->settings =  $this->company->quickbooks->settings;
   
        foreach($this->entities as $key => $entity) {
   
            if(!$this->syncGate($key, 'pull')) {
                continue;
            }

            $records = $this->qbs->sdk()->fetchRecords($entity);

            $this->processEntitySync($key, $records);

        }

    }
    
    /**
     * Determines whether a sync is allowed based on the settings
     *
     * @param  string $entity
     * @param  string $direction
     * @return bool
     */
    private function syncGate(string $entity, string $direction): bool
    {
        return (bool) $this->settings->{$entity}->sync && in_array($this->settings->{$entity}->direction, [$direction,'bidirectional']);
    }
    
    /**
     * Updates the gate for a given entity
     *
     * @param  string $entity
     * @return bool
     */
    private function updateGate(string $entity): bool
    {
        return (bool) $this->settings->{$entity}->sync && $this->settings->{$entity}->update_record;
    }

    /**
     * Processes the sync for a given entity
     *
     * @param  string $entity
     * @param  mixed $records
     * @return void
     */
    private function processEntitySync(string $entity, $records): void 
    {
        match($entity){
                // 'client' => $this->syncQbToNinjaClients($records),
            'product' => $this->qbs->product->syncToNinja($records),
                // 'invoice' => $this->syncQbToNinjaInvoices($records),
                // 'sales' => $this->syncQbToNinjaInvoices($records),
                // 'vendor' => $this->syncQbToNinjaVendors($records),
                // 'quote' => $this->syncInvoices($records),
                // 'expense' => $this->syncQbToNinjaExpenses($records),
                // 'purchase_order' => $this->syncInvoices($records),
                // 'payment' => $this->syncPayment($records), 
            default => false,
        };
    }

    private function syncQbToNinjaInvoices($records): void
    {
        nlog("invoice sync ". count($records));
        $invoice_transformer = new InvoiceTransformer($this->company);
        
        foreach($records as $record)
        {
            nlog($record);

            $ninja_invoice_data = $invoice_transformer->qbToNinja($record);

            nlog($ninja_invoice_data);

            $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];

            $client_id = $ninja_invoice_data['client_id'] ?? null;

            if(is_null($client_id))
                continue;

            unset($ninja_invoice_data['payment_ids']);

            if($invoice = $this->findInvoice($ninja_invoice_data))
            {
                $invoice->fill($ninja_invoice_data);
                $invoice->saveQuietly();

                $invoice = $invoice->calc()->getInvoice()->service()->markSent()->createInvitations()->save();
            
                foreach($payment_ids as $payment_id)
                {

                    $payment = $this->qbs->sdk->FindById('Payment', $payment_id);

                    $payment_transformer = new PaymentTransformer($this->company);

                    $transformed = $payment_transformer->qbToNinja($payment);
                    
                    $ninja_payment = $payment_transformer->buildPayment($payment);
                    $ninja_payment->service()->applyNumber()->save();

                    $paymentable = new \App\Models\Paymentable();
                    $paymentable->payment_id = $ninja_payment->id;
                    $paymentable->paymentable_id = $invoice->id;
                    $paymentable->paymentable_type = 'invoices';
                    $paymentable->amount = $transformed['applied'] + $ninja_payment->credits->sum('amount');
                    $paymentable->created_at = $ninja_payment->date; //@phpstan-ignore-line
                    $paymentable->save();

                    $invoice->service()->applyPayment($ninja_payment, $paymentable->amount);

                }

                if($record instanceof IPPSalesReceipt)
                {
                    $invoice->service()->markPaid()->save();
                }

            }

            $ninja_invoice_data = false;

        }

    }

    private function findInvoice(array $ninja_invoice_data): ?Invoice
    {
        $search = Invoice::query()
                            ->withTrashed()
                            ->where('company_id', $this->company->id)
                            // ->where('number', $ninja_invoice_data['number']);
                            ->where('sync->qb_id', $ninja_invoice_data['id']);

        if($search->count() == 0) {
            //new invoice
            $invoice = InvoiceFactory::create($this->company->id, $this->company->owner()->id);
            $invoice->client_id = $ninja_invoice_data['client_id'];

            return $invoice;
        } elseif($search->count() == 1) {
            return $this->settings->invoice->update_record ? $search->first() : null;
        }

        return null;

    }

    private function syncQbToNinjaClients(array $records): void
    {

        $client_transformer = new ClientTransformer($this->company);

        foreach($records as $record)
        {
            $ninja_client_data = $client_transformer->qbToNinja($record);

            if($client = $this->findClient($ninja_client_data))
            {
                $client->fill($ninja_client_data[0]);
                $client->saveQuietly();

                $contact = $client->contacts()->where('email', $ninja_client_data[1]['email'])->first();

                if(!$contact)
                {
                    $contact = ClientContactFactory::create($this->company->id, $this->company->owner()->id);
                    $contact->client_id = $client->id;
                    $contact->send_email = true;
                    $contact->is_primary = true;
                    $contact->fill($ninja_client_data[1]);
                    $contact->saveQuietly(); 
                }
                elseif($this->updateGate('client')){
                    $contact->fill($ninja_client_data[1]);
                    $contact->saveQuietly();
                }

            }

        }
    }

    private function syncQbToNinjaVendors(array $records): void
    {

        $transformer = new VendorTransformer($this->company);

        foreach($records as $record)
        {
            $ninja_data = $transformer->qbToNinja($record);

            if($vendor = $this->findVendor($ninja_data))
            {
                $vendor->fill($ninja_data[0]);
                $vendor->saveQuietly();

                $contact = $vendor->contacts()->where('email', $ninja_data[1]['email'])->first();

                if(!$contact)
                {
                    $contact = VendorContactFactory::create($this->company->id, $this->company->owner()->id);
                    $contact->vendor_id = $vendor->id;
                    $contact->send_email = true;
                    $contact->is_primary = true;
                    $contact->fill($ninja_data[1]);
                    $contact->saveQuietly(); 
                }
                elseif($this->updateGate('vendor')){
                    $contact->fill($ninja_data[1]);
                    $contact->saveQuietly();
                }

            }

        }
    }

    private function syncQbToNinjaExpenses(array $records): void
    {

        $transformer = new ExpenseTransformer($this->company);

        foreach($records as $record)
        {
            $ninja_data = $transformer->qbToNinja($record);

            if($expense = $this->findExpense($ninja_data))
            {
                $expense->fill($ninja_data);
                $expense->saveQuietly();
            }

        }
    }


    private function syncQbToNinjaProducts($records): void
    {
        $product_transformer = new ProductTransformer($this->company);

        foreach($records as $record)
        {
            $ninja_data = $product_transformer->qbToNinja($record);

            if($product = $this->findProduct($ninja_data['hash']))
            {
                $product->fill($ninja_data);
                $product->save();
            }
        }
    }

    private function findExpense(array $qb_data): ?Expense
    {
        $expense = $qb_data;

        $search = Expense::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('number', $expense['number']);
                        
        if($search->count() == 0) {
            return ExpenseFactory::create($this->company->id, $this->company->owner()->id);
        }
        elseif($search->count() == 1) {
            return $this->settings->expense->update_record ? $search->first() : null;
        }
        
        return null;
    }

    private function findVendor(array $qb_data) :?Vendor
    {
        $vendor = $qb_data[0];
        $contact = $qb_data[1];
        $vendor_meta = $qb_data[2];

        $search = Vendor::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where(function ($q) use ($vendor, $vendor_meta, $contact){

                            $q->where('vendor_hash', $vendor_meta['vendor_hash'])
                            ->orWhere('number', $vendor['number'])
                            ->orWhereHas('contacts', function ($q) use ($contact){
                                $q->where('email', $contact['email']);
                            });

                        });
                        
        if($search->count() == 0) {
            //new client
            return VendorFactory::create($this->company->id, $this->company->owner()->id);
        }
        elseif($search->count() == 1) {
            return $this->settings->vendor->update_record ? $search->first() : null;
        }
        
        return null;
    }

    private function findClient(array $qb_data) :?Client
    {
        $client = $qb_data[0];
        $contact = $qb_data[1];
        $client_meta = $qb_data[2];

        $search = Client::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where(function ($q) use ($client, $client_meta, $contact){

                            $q->where('client_hash', $client_meta['client_hash'])
                            ->orWhere('number', $client['number'])
                            ->orWhereHas('contacts', function ($q) use ($contact){
                                $q->where('email', $contact['email']);
                            });

                        });
                        
        if($search->count() == 0) {
            //new client
            $client = ClientFactory::create($this->company->id, $this->company->owner()->id);
            $client->client_hash = $client_meta['client_hash'];
            $client->settings = $client_meta['settings'];

            return $client;
        }
        elseif($search->count() == 1) {
            return $this->settings->client->update_record ? $search->first() : null;
        }
        
        return null;
    }

    

    public function middleware()
    {
        return [new WithoutOverlapping("qbs-{$this->company_id}-{$this->db}")];
    }

    public function failed($exception)
    {
        nlog("QuickbooksSync failed => ".$exception->getMessage());
        config(['queue.failed.driver' => null]);

    }
}
