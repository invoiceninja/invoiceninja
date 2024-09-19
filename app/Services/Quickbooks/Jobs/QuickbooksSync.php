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
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Factory\ClientContactFactory;
use App\DataMapper\QuickbooksSettings;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;
use App\Services\Quickbooks\Transformers\ProductTransformer;
use QuickBooksOnline\API\Data\IPPSalesReceipt;

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
        'quote' => 'Estimate',
        'purchase_order' => 'PurchaseOrder',
        'payment' => 'Payment',
        'sales' => 'SalesReceipt',
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
   
        nlog("here we go!");
        foreach($this->entities as $key => $entity) {
            if(!$this->syncGate($key, 'pull')) {
                continue;
            }

            $records = $this->qbs->sdk()->fetchRecords($entity);

            $this->processEntitySync($key, $records);

        }

    }

    private function syncGate(string $entity, string $direction): bool
    {
        return (bool) $this->settings[$entity]['sync'] && in_array($this->settings[$entity]['direction'], [$direction,'bidirectional']);
    }

    private function updateGate(string $entity): bool
    {
        return (bool) $this->settings[$entity]['sync'] && $this->settings[$entity]['update_record'];
    }

    // private function harvestQbEntityName(string $entity): string
    // {
    //     return $this->entities[$entity];
    // }

    private function processEntitySync(string $entity, $records)
    {
        match($entity){
            'client' => $this->syncQbToNinjaClients($records),
            'product' => $this->syncQbToNinjaProducts($records),
            'invoice' => $this->syncQbToNinjaInvoices($records),
            'sales' => $this->syncQbToNinjaInvoices($records),
            // 'vendor' => $this->syncQbToNinjaClients($records),
            // 'quote' => $this->syncInvoices($records),
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

                $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();
            
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
                    $paymentable->created_at = $ninja_payment->date;
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
                            ->where('number', $ninja_invoice_data['number']);

        if($search->count() == 0) {
            //new invoice
            $invoice = InvoiceFactory::create($this->company->id, $this->company->owner()->id);
            $invoice->client_id = $ninja_invoice_data['client_id'];

            return $invoice;
        } elseif($search->count() == 1) {
            return $this->settings['invoice']['update_record'] ? $search->first() : null;
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
                            ->orWhere('id_number', $client['id_number'])
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
            return $this->settings['client']['update_record'] ? $search->first() : null;
        }
        
        return null;
    }

    private function findProduct(string $key): ?Product
    {
        $search = Product::query()
                         ->withTrashed()
                         ->where('company_id', $this->company->id)
                         ->where('hash', $key);
             
        if($search->count() == 0) {
            //new product
            $product = ProductFactory::create($this->company->id, $this->company->owner()->id);
            $product->hash = $key;
            
            return $product;
        } elseif($search->count() == 1) {
            return $this->settings['product']['update_record'] ? $search->first() : null;
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
