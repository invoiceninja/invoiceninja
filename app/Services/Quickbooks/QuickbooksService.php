<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks;

use App\Models\Company;
use App\Models\TaxRate;
use App\DataMapper\QuickbooksSync;
use App\Services\Quickbooks\Models\QbQuote;
use App\Services\Quickbooks\Models\QbClient;
use QuickBooksOnline\API\Core\CoreConstants;
use App\Services\Quickbooks\Models\QbExpense;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\Models\QbPayment;
use App\Services\Quickbooks\Models\QbProduct;
use App\Services\Quickbooks\Models\QbTaxRate;
use QuickBooksOnline\API\DataService\DataService;
use App\Services\Quickbooks\Jobs\QuickbooksImport;
use App\Services\Quickbooks\Transformers\TaxRateTransformer;
use App\Services\Quickbooks\Transformers\IncomeAccountTransformer;
use App\Services\Quickbooks\Helpers\Helper;
use App\Helpers\Cache\Atomic;
use App\Services\Email\AdminEmail;
use App\Services\Email\EmailObject;
use App\Utils\Ninja;
use Illuminate\Mail\Mailables\Address;

class QuickbooksService
{
    public DataService $sdk;

    public QbInvoice $invoice;

    public QbProduct $product;

    public QbClient $client;

    public QbPayment $payment;

    public QbQuote $quote;

    public QbExpense $expense;

    public QuickbooksSync $settings;

    public QbTaxRate $tax_rate;

    public Helper $helper;

    private bool $testMode = true;

    private bool $try_refresh = true;

    /** Company IDs currently importing from QB — skip pushing them back. */
    public static array $importing = [];

    /**
     * In-memory cache of tax codes, indexed by ID.
     * Fetched once during initial sync and never persisted.
     *
     * @var array<string, array>
     */
    private ?array $tax_codes_cache = null;

    public function __construct(public Company $company)
    {
        $this->init();
    }
    
    /**
     * init
     *
     * Initializes the Quickbooks service by configuring the SDK and checking the token.
     * 
     * @return self
     */
    private function init(): self
    {

        if (config('services.quickbooks.client_id')) {
            $config = [
                'ClientID' => config('services.quickbooks.client_id'),
                'ClientSecret' => config('services.quickbooks.client_secret'),
                'auth_mode' => 'oauth2',
                'scope' => "com.intuit.quickbooks.accounting",
                'RedirectURI' => config('services.quickbooks.redirect'),
                'baseUrl' => $this->testMode ? CoreConstants::SANDBOX_DEVELOPMENT : CoreConstants::QBO_BASEURL,
            ];

            // Don't merge expired tokens when reconnection is required
            // This allows getAuthorizationUrl() to work correctly
            $requires_reconnect = $this->company->quickbooks && $this->company->quickbooks->requires_reconnect;
            
            if (!$requires_reconnect) {
                $config = array_merge($config, $this->ninjaAccessToken());
            }

            $this->sdk = DataService::Configure($config);

            $this->sdk->enableLog();
            $this->sdk->setMinorVersion("75");
            $this->sdk->throwExceptionOnError(true);

            if (!$requires_reconnect) {
            $this->checkToken();
            }
        }

        $this->invoice = new QbInvoice($this);

        $this->quote = new QbQuote($this);

        $this->product = new QbProduct($this);

        $this->client = new QbClient($this);

        $this->payment = new QbPayment($this);

        $this->expense = new QbExpense($this);

        $this->tax_rate = new QbTaxRate($this);

        $this->helper = new Helper($this->company, $this);

        $this->settings = $this->company->quickbooks->settings;

        return $this;
    }

    /**
     * Refresh the service after OAuth token has been updated.
     * This reloads the company from the database and reinitializes the SDK
     * with the new access token.
     *
     * @return self
     */
    public function refresh(): self
    {
        // Reload company from database to get fresh token data
        $this->company = $this->company->fresh();

        // Reinitialize the SDK with the updated token
        $this->init();

        return $this;
    }

    /**
     * checkToken
     *
     * Checks if the Quickbooks token is valid and refreshes it if it is not
     *
     * @return self
     */
    private function checkToken(): self
    {

        if (!$this->company->quickbooks || $this->company->quickbooks->accessTokenExpiresAt == 0 || $this->company->quickbooks->accessTokenExpiresAt > time()) {
            return $this;
        }

        // Access token is expired, check if we can refresh it
        if ($this->company->quickbooks->accessTokenExpiresAt && $this->company->quickbooks->accessTokenExpiresAt < time() && $this->try_refresh) {
            
            // Check if refresh token is also expired - if so, don't attempt refresh
            $refresh_token_expired = $this->company->quickbooks->refreshTokenExpiresAt > 0 
                && $this->company->quickbooks->refreshTokenExpiresAt < time();
            
            if ($refresh_token_expired) {
                $this->markRequiresReconnect();
                nlog('Quickbooks tokens expired (both access and refresh) => ' . $this->company->company_key);
                throw new \Exception('Quickbooks tokens expired (both access and refresh)');
            }

            // Refresh token is still valid, attempt to refresh
            try {
                $this->sdk()->refreshToken($this->company->quickbooks->refresh_token);
            } catch (\Throwable $e) {
                // Only log and disconnect if the error is not about expired refresh token
                // If refresh token is expired, we've already checked above, so this is a different error
                $error_message = $e->getMessage();
                if (str_contains($error_message, 'invalid_grant') || str_contains($error_message, 'refresh token')) {
                    // Refresh token is invalid/expired - don't try to disconnect (which would also fail)
                    nlog('Quickbooks refresh token invalid/expired => ' . $this->company->company_key);
                    $this->markRequiresReconnect();
                    throw new \Exception('Quickbooks refresh token invalid/expired');
                }
                
                nlog("QB: failure to refresh token: " . $error_message);
                // Only attempt disconnect if it's not a token expiration issue
                // Disconnect will try to revoke the token, which will fail if token is expired
                // So we skip disconnect for token-related errors
                if (!str_contains($error_message, 'token') && !str_contains($error_message, '401') && !str_contains($error_message, 'AuthenticationFailed')) {
                    $this->disconnect();
                }
                return $this;
            }

            $this->company = $this->company->fresh();
            $this->try_refresh = false;
            $this->init();

            return $this;
        }

        nlog('Quickbooks token expired and could not be refreshed => ' . $this->company->company_key);

        throw new \Exception('Quickbooks token expired and could not be refreshed');

    }

    private function markRequiresReconnect(): void
    {
        if ($this->company->quickbooks) {
            $this->company->quickbooks->requires_reconnect = true;
            $this->company->save();

            $this->notifyOwnerTokenExpired();
        }
    }

    private function notifyOwnerTokenExpired(): void
    {
        $cache_key = "qb_token_expired_notified:{$this->company->company_key}";

        if (!Atomic::set($cache_key, true, 60 * 60 * 24)) {
            return;
        }

        try {
            $mo = new EmailObject();
            $mo->subject = ctrans('texts.quickbooks_requires_reauth');
            $mo->body = ctrans('texts.quickbooks_requires_reauth_body');
            $mo->text_body = ctrans('texts.quickbooks_requires_reauth_body');
            $mo->company_key = $this->company->company_key;
            $mo->html_template = 'email.template.admin';
            $mo->to = [new Address($this->company->owner()->email, $this->company->owner()->present()->name())];
            $mo->url = Ninja::isHosted() ? config('ninja.react_url') . '/#/settings/integrations/quickbooks' : config('ninja.app_url');
            $mo->button = ctrans('texts.quickbooks_reconnect');
            $mo->settings = $this->company->settings;
            $mo->company = $this->company;
            $mo->logo = $this->company->present()->logo();

            AdminEmail::dispatch($mo, $this->company);
        } catch (\Exception $e) {
            nlog("Failed to send QuickBooks token expired notification: " . $e->getMessage());
        }
    }

    /**
     * ninjaAccessToken
     *
     * @return array
     */
    private function ninjaAccessToken(): array
    {
        return $this->company->quickbooks->accessTokenExpiresAt > 0 ? [
            'accessTokenKey' => $this->company->quickbooks->accessTokenKey,
            'refreshTokenKey' => $this->company->quickbooks->refresh_token,
            'QBORealmID' => $this->company->quickbooks->realmID,
        ] : [];
    }

    /**
     * sdk
     *
     * Wrapper class for fluent type accessors
     * 
     * @return SdkWrapper
     */
    public function sdk(): SdkWrapper
    {
        return new SdkWrapper($this->sdk, $this->company);
    }

    /**
     * Performs an initial sync of select entities from Quickbooks to Invoice Ninja.
     *
     * @return void
     */
    public function syncFromQb(): void
    {
        QuickbooksImport::dispatch($this->company->id, $this->company->db);
    }

    /**
     * findEntityById
     *
     * Returns a Quickbooks entity by ID.
     * 
     * @param  string $entity
     * @param  string $id
     * @return mixed
     */
    public function findEntityById(string $entity, string $id): mixed
    {
        return $this->sdk->FindById($entity, $id);
    }

    /**
     * query
     *
     * Returns an array (or null) of Quickbooks entities.
     * 
     * @param  string $query
     * @return mixed
     */
    public function query(string $query): mixed
    {
        return $this->sdk->Query($query);
    }

    /**
     * Flag to determine if a sync is allowed in either direction
     *
     * @param  string $entity
     * @param  \App\Enum\SyncDirection $direction
     * @return bool
     */
    public function syncable(string $entity, \App\Enum\SyncDirection $direction): bool
    {
        return isset($this->settings->{$entity}->direction) && ($this->settings->{$entity}->direction === $direction || $this->settings->{$entity}->direction === \App\Enum\SyncDirection::BIDIRECTIONAL);
    }


    // [
    //     QuickBooksOnline\API\Data\IPPAccount {#7706
    //       +Id: "30",
    //       +SyncToken: "0",
    //       +MetaData: QuickBooksOnline\API\Data\IPPModificationMetaData {#7707
    //         +CreatedByRef: null,
    //         +CreateTime: "2024-05-22T14:46:30-07:00",
    //         +LastModifiedByRef: null,
    //         +LastUpdatedTime: "2024-05-22T14:46:30-07:00",
    //         +LastChangedInQB: null,
    //         +Synchronized: null,
    //       },
    //       +CustomField: null,
    //       +AttachableRef: null,
    //       +domain: null,
    //       +status: null,
    //       +sparse: null,
    //       +Name: "Uncategorized Income",
    //       +SubAccount: "false",
    //       +ParentRef: null,
    //       +Description: null,
    //       +FullyQualifiedName: "Uncategorized Income",
    //       +AccountAlias: null,
    //       +TxnLocationType: null,
    //       +Active: "true",
    //       +Classification: "Revenue",
    //       +AccountType: "Income",
    //       +AccountSubType: "ServiceFeeIncome",
    //       +AccountPurposes: null,
    //       +AcctNum: null,
    //       +AcctNumExtn: null,
    //       +BankNum: null,
    //       +OpeningBalance: null,
    //       +OpeningBalanceDate: null,
    //       +CurrentBalance: "0",
    //       +CurrentBalanceWithSubAccounts: "0",
    //       +CurrencyRef: "USD",
    //       +TaxAccount: null,
    //       +TaxCodeRef: null,
    //       +OnlineBankingEnabled: null,
    //       +FIName: null,
    //       +JournalCodeRef: null,
    //       +AccountEx: null,
    //     },
    //   ]
    /**
     * Fetch income accounts from QuickBooks.
     *
     * @return array Array of account objects with 'Id', 'Name', 'AccountType', etc.
     */
    public function fetchIncomeAccounts(): array
    {
        try {
            if (!$this->sdk) {
                return [];
            }

            $query = "SELECT * FROM Account WHERE AccountType = 'Income' AND Active = true";
            $accounts = $this->sdk->Query($query);


            $iat = new IncomeAccountTransformer();
            $income_accounts = $iat->transformMany($accounts ?? []); //@phpstan-ignore-line return type is @array - but they also spec NULL as well

            return $income_accounts;
        } catch (\Exception $e) {
            nlog("Error fetching income accounts: {$e->getMessage()}");
            return [];
        }
    }


    // [
    //         QuickBooksOnline\API\Data\IPPAccount {#7709
    //       +Id: "57",
    //       +SyncToken: "0",
    //       +MetaData: QuickBooksOnline\API\Data\IPPModificationMetaData {#7698
    //         +CreatedByRef: null,
    //         +CreateTime: "2024-05-27T10:17:24-07:00",
    //         +LastModifiedByRef: null,
    //         +LastUpdatedTime: "2024-05-27T10:17:24-07:00",
    //         +LastChangedInQB: null,
    //         +Synchronized: null,
    //       },
    //       +CustomField: null,
    //       +AttachableRef: null,
    //       +domain: null,
    //       +status: null,
    //       +sparse: null,
    //       +Name: "Workers Compensation",
    //       +SubAccount: "true",
    //       +ParentRef: "11",
    //       +Description: null,
    //       +FullyQualifiedName: "Insurance:Workers Compensation",
    //       +AccountAlias: null,
    //       +TxnLocationType: null,
    //       +Active: "true",
    //       +Classification: "Expense",
    //       +AccountType: "Expense",
    //       +AccountSubType: "Insurance",
    //       +AccountPurposes: null,
    //       +AcctNum: null,
    //       +AcctNumExtn: null,
    //       +BankNum: null,
    //       +OpeningBalance: null,
    //       +OpeningBalanceDate: null,
    //       +CurrentBalance: "0",
    //       +CurrentBalanceWithSubAccounts: "0",
    //       +CurrencyRef: "USD",
    //       +TaxAccount: null,
    //       +TaxCodeRef: null,
    //       +OnlineBankingEnabled: null,
    //       +FIName: null,
    //       +JournalCodeRef: null,
    //       +AccountEx: null,
    //     },
    //   ]
    /**
     * Fetch expense accounts from QuickBooks.
     *
     * @return array Array of account objects with 'Id', 'Name', 'AccountType', etc.
     */
    public function fetchExpenseAccounts(): array
    {
        try {
            if (!$this->sdk) {
                return [];
            }

            $query = "SELECT * FROM Account WHERE AccountType IN ('Expense', 'Cost of Goods Sold') AND Active = true";
            $accounts = $this->sdk->Query($query);

            return is_array($accounts) ? $accounts : []; //@phpstan-ignore-line return type is @array - but they also spec NULL
        } catch (\Exception $e) {
            nlog("Error fetching expense accounts: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Fetch all active TaxRates from QuickBooks.
     * TaxRates are read-only in QuickBooks and cannot be created via API.
     *
     * @return array Array of TaxRate objects with 'Id', 'Name', 'TaxRateDetails', etc.
     */
    public function fetchTaxRates(): array
    {
        try {
            if (!$this->sdk) {
                return [];
            }

            // $query = "SELECT * FROM TaxCode WHERE Active = true";
            $query = "SELECT * FROM TaxRate WHERE Active = true";
            $tax_rates = $this->sdk->Query($query);

            $tax_rate_transformer = new TaxRateTransformer();
            $tax_rates = $tax_rate_transformer->transformMany($tax_rates ?? []); //@phpstan-ignore-line return type is @array - but they also spec NULL as well

            return $tax_rates;

        } catch (\Exception $e) {
            nlog("Error fetching tax rates: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Fetch all active TaxCodes from QuickBooks.
     * TaxCodes define which tax rates apply to items/products.
     *
     * @return array Array of TaxCode objects with 'Id', 'Name', 'SalesTaxRateList', etc.
     */
    public function fetchTaxCodes(): array
    {
        try {
            if (!$this->sdk) {
                return [];
            }

            $query = "SELECT * FROM TaxCode WHERE Active = true";
            $tax_codes = $this->sdk->Query($query);

            return is_array($tax_codes) ? $tax_codes : []; //@phpstan-ignore-line return type is @array - but they also spec NULL

        } catch (\Exception $e) {
            nlog("Error fetching tax codes: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Load tax codes into memory cache.
     * 
     * Called once during initial sync. 
     * Tax codes are never persisted.
     *
     * Tax codes allow us to reference exact Tax Rates for a given Tax Code.
     * 
     * @return self
     */
    public function loadTaxCodes(): self
    {
        if ($this->tax_codes_cache !== null) {
            return $this;
        }

        $tax_codes = $this->fetchTaxCodes();

        $this->tax_codes_cache = [];

        // Convert tax codes to array format and index by ID for easy lookup
        foreach ($tax_codes as $tax_code) {
            // Convert object to array if needed
            $tax_code_array = is_object($tax_code) ? json_decode(json_encode($tax_code), true) : $tax_code;
            if (isset($tax_code_array['Id'])) {
                $tax_code_id = is_array($tax_code_array['Id']) ? ($tax_code_array['Id']['value'] ?? $tax_code_array['Id']) : $tax_code_array['Id'];
                $this->tax_codes_cache[$tax_code_id] = $tax_code_array;
            }
        }

        return $this;
    }

    /**
     * Get a TaxCode by ID from the in-memory cache.
     * Returns null if tax codes haven't been loaded or if the ID doesn't exist.
     *
     * @param string $tax_code_id The QuickBooks TaxCode ID
     * @return array|null The TaxCode as an array, or null if not found
     */
    public function getTaxCode(?string $tax_code_id): ?array
    {
        if(!$this->tax_codes_cache) {
            $this->loadTaxCodes();
        }

        if (empty($tax_code_id) || $this->tax_codes_cache === null) {
            return null;
        }

        return $this->tax_codes_cache[$tax_code_id] ?? null;
    }

    /**
     * Verify the current token can authenticate with QuickBooks.
     * Performs a minimal API call; stub this in tests to avoid real API calls.
     *
     * @return bool True if token is valid and can authenticate
     */
    public function isTokenValid(): bool
    {
        try {
            if (! isset($this->sdk) || ! $this->sdk) {
                return false;
            }
            $this->sdk->Query('SELECT Id FROM CompanyInfo MAXRESULTS 1');
            return true;
        } catch (\Exception $e) {
            nlog('Quickbooks token validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format accounts for UI dropdown consumption.
     *
     * @param array $accounts Raw account objects from QuickBooks API
     * @return array Formatted array with 'value' (ID) and 'label' (Name) for each account
     */
    public function formatAccountsForDropdown(array $accounts): array
    {
        $formatted = [];

        foreach ($accounts as $account) {
            $id = is_object($account) && isset($account->Id)
                ? (string) $account->Id
                : (is_array($account) && isset($account['Id']) ? (string) $account['Id'] : null);

            $name = is_object($account) && isset($account->Name)
                ? (string) $account->Name
                : (is_array($account) && isset($account['Name']) ? (string) $account['Name'] : '');

            if ($id && $name) {
                $formatted[] = [
                    'value' => $id,
                    'label' => $name,
                    'account_type' => is_object($account) && isset($account->AccountType)
                        ? (string) $account->AccountType
                        : (is_array($account) && isset($account['AccountType']) ? (string) $account['AccountType'] : ''),
                ];
            }
        }

        return $formatted;
    }

    /**
     * syncTaxRates
     *
     * Syncs tax rates from Quickbooks to Invoice Ninja
     *
     * @return void
     */
    public function syncTaxRates(): void
    {

        $tax_rates = $this->fetchTaxRates();

        $this->company->quickbooks->settings->tax_rate_map = $tax_rates;
        $this->company->save();

        foreach ($tax_rates as $tax_rate) {
            if (TaxRate::where('company_id', $this->company->id)->where('name', $tax_rate['name'])->where('rate', $tax_rate['rate'])->doesntExist()) {
                $tr = new TaxRate();
                $tr->company_id = $this->company->id;
                $tr->user_id = $this->company->owner()->id;
                $tr->name = $tax_rate['name'];
                $tr->rate = $tax_rate['rate'];
                $tr->save();
            }
        }

    }
    
    /**
     * getIncomeAccountId
     *
     * Returns the default income account ID from the Quickbooks settings.
     * 
     * @return string
     */
    public function getIncomeAccountId(): ?string
    {
        return $this->company->quickbooks->settings->qb_income_account_id;
    }

    /**
     * disconnect
     *
     * Revokes the current token.
     * 
     * @return self
     */
    public function disconnect(): self
    {

        try {
            $this->sdk()->revokeAccessToken();
        } catch (\Throwable $e) {
            nlog("QB: failure to revoke token during disconnect:: " . $e->getMessage());
        }

        $this->company->quickbooks = null;
        $this->company->save();

        return $this;

    }
    
    /**
     * companySync
     *
     * Syncs the company information from Quickbooks to Invoice Ninja
     *
     * @return self
     */
    public function companySync(): self
    {

        $companyInfo = $this->sdk()->company();

        $income_accounts = $this->fetchIncomeAccounts();
        $tax_rates = $this->fetchTaxRates();
        $company_preferences = $this->sdk()->getPreferences();
        $automatic_taxes = data_get($company_preferences, 'TaxPrefs.PartnerTaxEnabled', false);

        $default_income_account = strlen($this->company->quickbooks->settings->qb_income_account_id ?? '') >= 1 ? $this->company->quickbooks->settings->qb_income_account_id : ($income_accounts[0]['id'] ?? null);
        
        $this->company->quickbooks->settings->income_account_map = $income_accounts;
        $this->company->quickbooks->settings->qb_income_account_id = $default_income_account;
        $this->company->quickbooks->companyName = $companyInfo->CompanyName ?? '';
        $this->company->quickbooks->settings->automatic_taxes = $automatic_taxes;

        // Extract QB company country for region-aware tax code handling
        $qb_country = $this->extractCompanyCountry($companyInfo);
        $this->company->quickbooks->settings->country = $qb_country;

        // Resolve TaxCode IDs for multi-region support (US uses 'TAX'/'NON', CA/AU/UK use numeric IDs)
        // Build TaxRate→TaxCode map so each line item can resolve the correct TaxCodeRef
        $tax_codes = $this->fetchTaxCodes();
        $default_taxable_code = null;
        $default_exempt_code = null;
        $tax_rate_to_tax_code = []; // TaxRate ID → TaxCode ID (sales only)

        foreach ($tax_codes as $tax_code) {
            $tax_code_array = is_object($tax_code) ? json_decode(json_encode($tax_code), true) : $tax_code;

            // Extract Id — IPPid serializes to {"value": "1"} or plain string
            $raw_id = data_get($tax_code_array, 'Id');
            $tc_id = is_array($raw_id) ? (string) ($raw_id['value'] ?? '') : (string) ($raw_id ?? '');
            if ($tc_id === '') {
                continue;
            }

            $name = (string) data_get($tax_code_array, 'Name', '');
            $taxable = data_get($tax_code_array, 'Taxable');
            $is_taxable = $taxable === true || $taxable === 'true' || $taxable === 1 || $taxable === '1';

            nlog("QB TaxCode: id={$tc_id} name={$name} taxable=" . var_export($taxable, true));

            // Resolve exempt code (first non-taxable TaxCode)
            if (!$is_taxable && $default_exempt_code === null) {
                $default_exempt_code = $tc_id;
            }

            // Extract SalesTaxRateList → map each TaxRate ID to this TaxCode ID
            $sales_rate_list = data_get($tax_code_array, 'SalesTaxRateList.TaxRateDetail', []);
            if (!is_array($sales_rate_list) || (!isset($sales_rate_list[0]) && !empty($sales_rate_list))) {
                $sales_rate_list = [$sales_rate_list]; // single entry
            }

            foreach ($sales_rate_list as $rate_detail) {
                $rate_ref = data_get($rate_detail, 'TaxRateRef');
                $tr_id = is_array($rate_ref) ? (string) ($rate_ref['value'] ?? '') : (string) ($rate_ref ?? '');
                if ($tr_id !== '' && !isset($tax_rate_to_tax_code[$tr_id])) {
                    $tax_rate_to_tax_code[$tr_id] = $tc_id;
                    nlog("QB TaxRate→TaxCode: rate_id={$tr_id} → tax_code_id={$tc_id} ({$name})");
                }
            }
        }

        // Fallback exempt code by common names
        if ($default_exempt_code === null && !empty($tax_codes)) {
            $exempt_names = ['e', 'exempt', 'non', 'tax exempt', 'z', 'zero', 'zero-rated', 'free', 'out of scope'];
            foreach ($tax_codes as $tax_code) {
                $tax_code_array = is_object($tax_code) ? json_decode(json_encode($tax_code), true) : $tax_code;
                $raw_id = data_get($tax_code_array, 'Id');
                $tc_id = is_array($raw_id) ? (string) ($raw_id['value'] ?? '') : (string) ($raw_id ?? '');
                $name = strtolower(trim((string) data_get($tax_code_array, 'Name', '')));
                if ($tc_id !== '' && in_array($name, $exempt_names, true)) {
                    $default_exempt_code = $tc_id;
                    nlog("QB TaxCode: resolved exempt code by name: id={$tc_id} name={$name}");
                    break;
                }
            }
        }

        // Augment tax_rate_map entries with their resolved TaxCode ID
        foreach ($tax_rates as &$rate_entry) {
            $tr_id = (string) ($rate_entry['id'] ?? '');
            $rate_entry['tax_code_id'] = $tax_rate_to_tax_code[$tr_id] ?? null;
        }
        unset($rate_entry);

        // Derive default taxable code: pick the TaxCode with the highest non-zero sales rate
        if (!empty($tax_rate_to_tax_code)) {
            $best_rate = 0;
            foreach ($tax_rates as $rate_entry) {
                if (($rate_entry['tax_code_id'] ?? null) && floatval($rate_entry['rate']) > $best_rate) {
                    $best_rate = floatval($rate_entry['rate']);
                    $default_taxable_code = $rate_entry['tax_code_id'];
                }
            }
        }

        // US companies MUST use "TAX"/"NON" as TaxCodeRef — never numeric IDs.
        // Non-US (CA/AU/GB) use the resolved numeric TaxCode IDs.
        if ($qb_country === 'US') {
            $default_taxable_code = 'TAX';
            $default_exempt_code = 'NON';
        }

        nlog("QB TaxCode resolution: country={$qb_country} taxable={$default_taxable_code} exempt={$default_exempt_code} rate_map_count=" . count($tax_rate_to_tax_code));

        $this->company->quickbooks->settings->tax_rate_map = $tax_rates;
        $this->company->quickbooks->settings->default_taxable_code = $default_taxable_code;
        $this->company->quickbooks->settings->default_exempt_code = $default_exempt_code;

        // Fetch and cache payment methods for payment type mapping
        $payment_methods = $this->fetchPaymentMethods();
        $this->company->quickbooks->settings->payment_method_map = $payment_methods;

        $this->company->save();

        // Iterate through the Quickbooks tax rates and create new Invoice Ninja tax rates
        foreach ($tax_rates as $tax_rate) {
        
            $tr = TaxRate::firstOrNew(
                ['name' => $tax_rate['name'], 'company_id' => $this->company->id, 'rate' => $tax_rate['rate']],
            []
            );

            $tr->company_id = $this->company->id;
            $tr->user_id = $this->company->owner()->id;
            $tr->save();
        
        }

        return $this;

    }

    /**
     * Fetch all active PaymentMethods from QuickBooks.
     *
     * @return array Array of ['id' => string, 'name' => string, 'type' => string]
     */
    public function fetchPaymentMethods(): array
    {
        try {
            if (!$this->sdk) {
                return [];
            }

            $query = "SELECT * FROM PaymentMethod WHERE Active = true";
            $methods = $this->sdk->Query($query);

            if (!is_array($methods)) {
                return [];
            }

            $result = [];
            foreach ($methods as $method) {
                $id = is_object($method) ? (string) ($method->Id ?? '') : (string) ($method['Id'] ?? '');
                $name = is_object($method) ? (string) ($method->Name ?? '') : (string) ($method['Name'] ?? '');
                $type = is_object($method) ? (string) ($method->Type ?? '') : (string) ($method['Type'] ?? '');

                if ($id && $name) {
                    $result[] = [
                        'id' => $id,
                        'name' => $name,
                        'type' => $type,
                    ];
                }
            }

            return $result;
        } catch (\Exception $e) {
            nlog("Error fetching payment methods: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Extract the country code from QuickBooks CompanyInfo.
     *
     * Checks CompanyAddr.Country, LegalAddr.Country, and root-level Country.
     * Returns a normalized 2-letter ISO code (e.g. "US", "CA", "AU", "GB").
     * Falls back to "US" if no country can be determined.
     */
    private function extractCompanyCountry(mixed $companyInfo): string
    {
        $country_raw = data_get($companyInfo, 'CompanyAddr.Country')
            ?? data_get($companyInfo, 'CompanyAddr.CountryCode')
            ?? data_get($companyInfo, 'LegalAddr.Country')
            ?? data_get($companyInfo, 'LegalAddr.CountryCode')
            ?? data_get($companyInfo, 'Country')
            ?? '';

        $country_raw = trim((string) $country_raw);

        if ($country_raw === '') {
            nlog("QB companySync: No country found in CompanyInfo, defaulting to 'US'");
            return 'US';
        }

        $normalized = strtoupper($country_raw);
        $country_map = [
            'US' => 'US', 'USA' => 'US', 'UNITED STATES' => 'US', 'UNITED STATES OF AMERICA' => 'US',
            'CA' => 'CA', 'CAN' => 'CA', 'CANADA' => 'CA',
            'AU' => 'AU', 'AUS' => 'AU', 'AUSTRALIA' => 'AU',
            'GB' => 'GB', 'GBR' => 'GB', 'UNITED KINGDOM' => 'GB', 'UK' => 'GB',
            'IN' => 'IN', 'IND' => 'IN', 'INDIA' => 'IN',
            'FR' => 'FR', 'FRA' => 'FR', 'FRANCE' => 'FR',
            'DE' => 'DE', 'DEU' => 'DE', 'GERMANY' => 'DE',
        ];

        $result = $country_map[$normalized] ?? $normalized;

        nlog("QB companySync: Resolved company country '{$country_raw}' to '{$result}'");

        return $result;
    }
}
