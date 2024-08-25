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

use App\Factory\ClientFactory;
use App\Models\Client;
use App\Models\Company;
use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\DataService\DataService;
use App\Services\Import\Quickbooks\Transformers\ClientTransformer;

// quickbooks_realm_id
// quickbooks_refresh_token
// quickbooks_refresh_expires
class QuickbooksService
{
    public DataService $sdk;
    
    private $entities = [
        'client' => 'Customer',
        'invoice' => 'Invoice',
        'quote' => 'Estimate',
        'purchase_order' => 'PurchaseOrder',
        'payment' => 'Payment',
        'product' => 'Item',
    ];

    private bool $testMode = true;

    private array $settings = [];

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
    public function sync()
    {
        //syncable_records.

        foreach($this->entities as $entity)
        {

            $records = $this->sdk()->fetchRecords($entity);

            $this->processEntitySync($entity, $records);
            

        }

    }

    private function processEntitySync(string $entity, $records)
    {
        match($entity){
            'client' => $this->syncQbToNinjaClients($records),
            // 'vendor' => $this->syncQbToNinjaClients($records),
            // 'invoice' => $this->syncInvoices($records),
            // 'quote' => $this->syncInvoices($records),
            // 'purchase_order' => $this->syncInvoices($records),
            // 'payment' => $this->syncPayment($records), 
            // 'product' => $this->syncItem($records),
        };
    }

    private function syncQbToNinjaClients(array $records)
    {
        foreach($records as $record)
        {
            $ninja_client_data = new ClientTransformer($record);

            if($client = $this->findClient($ninja_client_data))
            {
                $client->fill($ninja_client_data[0]);
            }

        }
    }

    private function findClient(array $qb_data)
    {
        $client = $qb_data[0];
        $contact = $qb_data[1];
        $client_meta = $qb_data[2];

        $search = Client::query()
                        ->withTrashed()
                        ->where('company', $this->company->id)
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
            // ? sync / update
        }
        else {
            //potentially multiple matching clients?
        }
    }
}
