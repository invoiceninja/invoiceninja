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

namespace App\Services\Import\Quickbooks;

use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\DataService\DataService;
use App\Services\Import\Quickbooks\Transformers\ClientTransformer;
use App\Services\Import\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Import\Quickbooks\Transformers\PaymentTransformer;
use App\Services\Import\Quickbooks\Transformers\ProductTransformer;

// quickbooks_realm_id
// quickbooks_refresh_token
// quickbooks_refresh_expires
class QuickbooksService
{
    public DataService $sdk;
    
    private $entities = [
        'product' => 'Item',
        'client' => 'Customer',
        'invoice' => 'Invoice',
        'quote' => 'Estimate',
        'purchase_order' => 'PurchaseOrder',
        'payment' => 'Payment',
    ];

    private bool $testMode = true;

    private mixed $settings;

    public function __construct(private Company $company)
    {
        $this->init();
        $this->settings = $this->company->quickbooks->settings;
    }

    private function init(): self
    {

        $config = [
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'auth_mode' => 'oauth2',
            'scope' => "com.intuit.quickbooks.accounting",
            // 'RedirectURI' => 'https://developer.intuit.com/v2/OAuth2Playground/RedirectUrl',
            'RedirectURI' => $this->testMode ? 'https://above-distinctly-teal.ngrok-free.app/quickbooks/authorized' : 'https://invoicing.co/quickbooks/authorized',
            'baseUrl' => $this->testMode ?  CoreConstants::SANDBOX_DEVELOPMENT : CoreConstants::QBO_BASEURL,
        ];

        $merged = array_merge($config, $this->ninjaAccessToken());
        
        $this->sdk = DataService::Configure($merged);

        $this->sdk->setLogLocation(storage_path("logs/quickbooks.log"));
        $this->sdk->enableLog();

        $this->sdk->setMinorVersion("73");
        $this->sdk->throwExceptionOnError(true);

        return $this;
    }

    private function ninjaAccessToken()
    {
        return isset($this->company->quickbooks->accessTokenKey) ? [
            'accessTokenKey' => $this->company->quickbooks->accessTokenKey,
            'refreshTokenKey' => $this->company->quickbooks->refresh_token,
            'QBORealmID' => $this->company->quickbooks->realmID,
        ] : [];
    }

    public function sdk(): SdkWrapper
    {
        return new SdkWrapper($this->sdk, $this->company);
    }
        
    /**
     * //@todo - refactor to a job
     *
     * @return void
     */
    public function syncFromQb()
    {
        //syncable_records.

        foreach($this->entities as $key => $entity)
        {
            if(!$this->syncGate($key, 'pull'))
                continue;

            $records = $this->sdk()->fetchRecords($entity);

            nlog(json_encode($records));

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

    private function harvestQbEntityName(string $entity): string
    {
        return $this->entities[$entity];
    }

    private function processEntitySync(string $entity, $records)
    {
        match($entity){
            // 'client' => $this->syncQbToNinjaClients($records),
            'product' => $this->syncQbToNinjaProducts($records),
            // 'invoice' => $this->syncQbToNinjaInvoices($records),
            // 'vendor' => $this->syncQbToNinjaClients($records),
            // 'quote' => $this->syncInvoices($records),
            // 'purchase_order' => $this->syncInvoices($records),
            // 'payment' => $this->syncPayment($records), 
            default => false,
        };
    }

    private function syncQbToNinjaInvoices($records): void
    {
        $invoice_transformer = new InvoiceTransformer($this->company);
        
        foreach($records as $record)
        {
            $ninja_invoice_data = $invoice_transformer->qbToNinja($record);

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

                    $payment = $this->sdk->FindById('Payment', $payment_id);
                    $payment_transformer = new PaymentTransformer($this->company);

                    $transformed = $payment_transformer->qbToNinja($payment);
                    
                    $ninja_payment = $payment_transformer->buildPayment($payment);
                    $ninja_payment->service()->applyNumber()->save();

                    $paymentable = new \App\Models\Paymentable();
                    $paymentable->payment_id = $ninja_payment->id;
                    $paymentable->paymentable_id = $invoice->id;
                    $paymentable->paymentable_type = 'invoices';
                    $paymentable->amount = $transformed['applied'];
                    $paymentable->save();

                    $invoice->service()->applyPayment($ninja_payment, $transformed['applied']);

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

            return $product;
        } elseif($search->count() == 1) {
            return $this->settings['product']['update_record'] ? $search->first() : null;
        }

        return null;


    }
}
