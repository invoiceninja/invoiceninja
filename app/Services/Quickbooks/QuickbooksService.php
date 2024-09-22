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

namespace App\Services\Quickbooks;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Factory\ClientContactFactory;
use App\DataMapper\QuickbooksSettings;
use QuickBooksOnline\API\Core\CoreConstants;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\Models\QbProduct;
use App\Services\Quickbooks\Jobs\QuickbooksSync;
use QuickBooksOnline\API\DataService\DataService;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;
use App\Services\Quickbooks\Transformers\ProductTransformer;

class QuickbooksService
{
    public DataService $sdk;

    public QbInvoice $invoice;

    public QbProduct $product;

    public array $settings;

    private bool $testMode = true;

    public function __construct(public Company $company)
    {
        $this->init();
    }

    private function init(): self
    {

        $config = [
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'auth_mode' => 'oauth2',
            'scope' => "com.intuit.quickbooks.accounting",
            // 'RedirectURI' => 'https://developer.intuit.com/v2/OAuth2Playground/RedirectUrl',
            'RedirectURI' => $this->testMode ? 'https://grok.romulus.com.au/quickbooks/authorized' : 'https://invoicing.co/quickbooks/authorized',
            'baseUrl' => $this->testMode ?  CoreConstants::SANDBOX_DEVELOPMENT : CoreConstants::QBO_BASEURL,
        ];

        $merged = array_merge($config, $this->ninjaAccessToken());
        
        $this->sdk = DataService::Configure($merged);

        $this->sdk->setLogLocation(storage_path("logs/quickbooks.log"));
        $this->sdk->enableLog();

        $this->sdk->setMinorVersion("73");
        $this->sdk->throwExceptionOnError(true);

        $this->invoice = new QbInvoice($this);
        
        $this->product = new QbProduct($this);

        $this->settings = $this->company->quickbooks->settings;
        
        return $this;
    }

    private function ninjaAccessToken(): array
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
     * 
     *
     * @return void
     */
    public function syncFromQb(): void
    {
        QuickbooksSync::dispatch($this->company->id, $this->company->db);
    }

}
