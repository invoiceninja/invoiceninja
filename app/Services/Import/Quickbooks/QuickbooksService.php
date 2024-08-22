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

use App\Models\Company;
use QuickBooksOnline\API\DataService\DataService;

// quickbooks_realm_id
// quickbooks_refresh_token
// quickbooks_refresh_expires
class QuickbooksService
{
    private DataService $sdk;

    private Auth $auth;

    public function __construct(private Company $company)
    {
        $this->init()
            ->auth();
    }

    private function init(): self
    {

        $this->sdk = DataService::Configure([
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'auth_mode' => 'oauth2',
            'scope' => "com.intuit.quickbooks.accounting",
            'RedirectURI' => 'https://developer.intuit.com/v2/OAuth2Playground/RedirectUrl',
            // 'RedirectURI' => route('quickbooks.authorized'),
        ]);

        // if (env('APP_DEBUG')) {
        //     $sdk->setLogLocation(storage_path("logs/quickbooks.log"));
        //     $sdk->enableLog();
        // }

        $this->sdk->setMinorVersion("73");
        $this->sdk->throwExceptionOnError(true);

        return $this;
    }

    private function auth(): self
    {
        $wrapper = new SdkWrapper($this->sdk);
        $this->auth = new Auth($wrapper);

        return $this;
    }

    public function getSdk(): DataService
    {
        return $this->sdk;
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }
}
