<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Util;

use App\DataMapper\Analytics\MigrationFailure;
use App\DataMapper\CompanySettings;
use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Factory\ClientFactory;
use App\Factory\CompanyLedgerFactory;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Factory\QuoteFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\TaxRateFactory;
use App\Factory\UserFactory;
use App\Factory\VendorFactory;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\ValidationRules\ValidCompanyGatewayFeesAndLimitsRule;
use App\Http\ValidationRules\ValidUserForCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\Ninja\CompanySizeCheck;
use App\Libraries\MultiDB;
use App\Mail\MigrationCompleted;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CreditRepository;
use App\Repositories\Migration\InvoiceMigrationRepository;
use App\Repositories\Migration\PaymentMigrationRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;
use App\Repositories\VendorContactRepository;
use App\Repositories\VendorRepository;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\Uploadable;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Turbo124\Beacon\Facades\LightLogs;

class Import implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use CompanyGatewayFeesAndLimitsSaver;
    use MakesHash;
    use CleanLineItems;
    use Uploadable;
    use SavesDocuments;
    /**
     * @var array
     */
    private $file_path; //the file path - using a different JSON parser here.

    /**
     * @var Company
     */
    private $company;

    private $token;

    /**
     * @var array
     */
    private $available_imports = [
        'account',
        'company',
        'users',
        'payment_terms',
        'tax_rates',
        'clients',
        'company_gateways',
        'client_gateway_tokens',
        'vendors',
        'projects',
        'products',
        'credits',
        'invoices',
        'recurring_invoices',
        'quotes',
        'payments',
        'expense_categories',
        'task_statuses',
        'expenses',
        'tasks',
        'documents',
    ];

    /**
     * @var User
     */
    private $user;

    /**
     * Custom list of resources to be imported.
     *
     * @var array
     */
    private $resources;

    /**
     * Local state manager for ids.
     *
     * @var array
     */
    private $ids = [];

    public $tries = 1;

    public $timeout = 0;

    // public $backoff = 86430;

    //  public $maxExceptions = 2;
    /**
     * Create a new job instance.
     *
     * @param array $data
     * @param Company $company
     * @param User $user
     * @param array $resources
     */
    public function __construct(string $file_path, Company $company, User $user, array $resources = [])
    {
        $this->file_path = $file_path;
        $this->company = $company;
        $this->user = $user;
        $this->resources = $resources;
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle()
    {
        set_time_limit(0);

        auth()->login($this->user, false);
        auth()->user()->setCompany($this->company);

        //   $jsonStream = \JsonMachine\JsonMachine::fromFile($this->file_path, "/data");
        $array = json_decode(file_get_contents($this->file_path), 1);
        $data = $array['data'];

        foreach ($this->available_imports as $import) {
            if (! array_key_exists($import, $data)) {
                //throw new ResourceNotAvailableForMigration("Resource {$key} is not available for migration.");
                info("Resource {$import} is not available for migration.");
                continue;
            }

            $method = sprintf('process%s', Str::ucfirst(Str::camel($import)));

            info("Importing {$import}");

            $this->{$method}($data[$import]);
        }

        $this->setInitialCompanyLedgerBalances();
        
        $this->fixClientBalances();

        Mail::to($this->user)
            ->send(new MigrationCompleted($this->company));

        /*After a migration first some basic jobs to ensure the system is up to date*/
        VersionCheck::dispatch();
        CompanySizeCheck::dispatch();

        info('Completed🚀🚀🚀🚀🚀 at '.now());
    }

    private function setInitialCompanyLedgerBalances()
    {
        Client::cursor()->each(function ($client) {
            $company_ledger = CompanyLedgerFactory::create($client->company_id, $client->user_id);
            $company_ledger->client_id = $client->id;
            $company_ledger->adjustment = $client->balance;
            $company_ledger->notes = 'Migrated Client Balance';
            $company_ledger->balance = $client->balance;
            $company_ledger->activity_id = Activity::CREATE_CLIENT;
            $company_ledger->save();

            $client->company_ledger()->save($company_ledger);
        });
    }

    private function processAccount(array $data) :void
    {
        if(array_key_exists('token', $data)){
            $this->token = $data['token'];
            unset($data['token']);
        }

        $account = $this->company->account;
        $account->fill($data);
        $account->save();
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processCompany(array $data): void
    {
        Company::unguard();

        if (
            $data['settings']['invoice_design_id'] > 9 ||
            $data['settings']['invoice_design_id'] > "9"
        ) {
            $data['settings']['invoice_design_id'] = 1;
        }

        $data = $this->transformCompanyData($data);

        $rules = (new UpdateCompanyRequest())->rules();

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        if (isset($data['account_id'])) {
            unset($data['account_id']);
        }

        if (isset($data['referral_code'])) {
            $account = $this->company->account;
            $account->referral_code = $data['referral_code'];
            $account->save();

            unset($data['referral_code']);
        }

        $company_repository = new CompanyRepository();
        $company_repository->save($data, $this->company);

        if (isset($data['settings']->company_logo) && strlen($data['settings']->company_logo) > 0) {
            try {
                $tempImage = tempnam(sys_get_temp_dir(), basename($data['settings']->company_logo));
                copy($data['settings']->company_logo, $tempImage);
                $this->uploadLogo($tempImage, $this->company, $this->company);
            } catch (\Exception $e) {
            }
        }

        Company::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
        $company_repository = null;
    }

    private function transformCompanyData(array $data): array
    {
        $company_settings = CompanySettings::defaults();

        if (array_key_exists('settings', $data)) {
            foreach ($data['settings'] as $key => $value) {
                if ($key == 'invoice_design_id' || $key == 'quote_design_id' || $key == 'credit_design_id') {
                    $value = $this->encodePrimaryKey($value);
                }

                if ($key == 'payment_terms' && $key = '') {
                    $value = -1;
                }

                $company_settings->{$key} = $value;
            }

            $data['settings'] = $company_settings;
        }

        return $data;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processTaxRates(array $data): void
    {
        TaxRate::unguard();

        $rules = [
            '*.name' => 'required',
            //'*.name' => 'required|distinct|unique:tax_rates,name,null,null,company_id,' . $this->company->id,
            '*.rate' => 'required|numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        foreach ($data as $resource) {
            $modified = $resource;
            $company_id = $this->company->id;
            $user_id = $this->processUserId($resource);

            if (isset($resource['user_id'])) {
                unset($resource['user_id']);
            }

            if (isset($resource['company_id'])) {
                unset($resource['company_id']);
            }

            $tax_rate = TaxRateFactory::create($this->company->id, $user_id);
            $tax_rate->fill($resource);

            $tax_rate->save();
        }

        TaxRate::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processUsers(array $data): void
    {
        User::unguard();

        $rules = [
            '*.first_name' => ['string'],
            '*.last_name' => ['string'],
            '*.email' => ['distinct'],
        ];

        // if (config('ninja.db.multi_db_enabled')) {
        //     array_push($rules['*.email'], new ValidUserForCompany());
        // }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $user_repository = new UserRepository();

        foreach ($data as $resource) {
            $modified = $resource;
            unset($modified['id']);
            unset($modified['password']); //cant import passwords.

            $user = $user_repository->save($modified, $this->fetchUser($resource['email']), true, true);

            $user_agent = array_key_exists('token_name', $resource) ?: request()->server('HTTP_USER_AGENT');

            CreateCompanyToken::dispatchNow($this->company, $user, $user_agent);

            $key = "users_{$resource['id']}";

            $this->ids['users'][$key] = [
                'old' => $resource['id'],
                'new' => $user->id,
            ];
        }

        User::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
        $user_repository = null;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processClients(array $data): void
    {
        Client::unguard();

        $contact_repository = new ClientContactRepository();
        $client_repository = new ClientRepository($contact_repository);

        foreach ($data as $key => $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);
            $modified['balance'] = $modified['balance'] ?: 0;
            $modified['paid_to_date'] = $modified['paid_to_date'] ?: 0;

            unset($modified['id']);
            unset($modified['contacts']);

            $client = $client_repository->save(
                $modified,
                ClientFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );

            $client->contacts()->forceDelete();

            if (array_key_exists('contacts', $resource)) { // need to remove after importing new migration.json
                $modified_contacts = $resource['contacts'];

                foreach ($modified_contacts as $key => $client_contacts) {
                    $modified_contacts[$key]['company_id'] = $this->company->id;
                    $modified_contacts[$key]['user_id'] = $this->processUserId($resource);
                    $modified_contacts[$key]['client_id'] = $client->id;
                    $modified_contacts[$key]['password'] = 'mysuperpassword'; // @todo, and clean up the code..
                    unset($modified_contacts[$key]['id']);
                }

                $saveable_contacts['contacts'] = $modified_contacts;

                $contact_repository->save($saveable_contacts, $client);

                //link contact ids

                foreach ($resource['contacts'] as $key => $old_contact) {
                    
                    $contact_match = ClientContact::where('contact_key', $old_contact['contact_key'])
                                                 ->where('company_id', $this->company->id)
                                                 ->where('client_id', $client->id)
                                                 ->withTrashed()
                                                 ->first();

                    if ($contact_match) {
                        
                        $this->ids['client_contacts']['client_contacts_'.$old_contact['id']] = [
                            'old' => $old_contact['id'],
                            'new' => $contact_match->id,
                        ];
                        
                    }
                }
            }

            $key = "clients_{$resource['id']}";

            $this->ids['clients'][$key] = [
                'old' => $resource['id'],
                'new' => $client->id,
            ];
        }

        Client::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $contact_repository = null;
        $client_repository = null;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processVendors(array $data): void
    {
        Vendor::unguard();

        $contact_repository = new VendorContactRepository();
        $vendor_repository = new VendorRepository($contact_repository);

        foreach ($data as $key => $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            unset($modified['id']);
            unset($modified['contacts']);

            $vendor = $vendor_repository->save(
                $modified,
                VendorFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );

            $vendor->contacts()->forceDelete();

            if (array_key_exists('contacts', $resource)) { // need to remove after importing new migration.json
                $modified_contacts = $resource['contacts'];

                foreach ($modified_contacts as $key => $vendor_contacts) {
                    $modified_contacts[$key]['company_id'] = $this->company->id;
                    $modified_contacts[$key]['user_id'] = $this->processUserId($resource);
                    $modified_contacts[$key]['vendor_id'] = $vendor->id;
                    $modified_contacts[$key]['password'] = 'mysuperpassword'; // @todo, and clean up the code..
                    unset($modified_contacts[$key]['id']);
                }

                $saveable_contacts['contacts'] = $modified_contacts;

                $contact_repository->save($saveable_contacts, $vendor);
            }

            $key = "vendors_{$resource['id']}";

            $this->ids['vendors'][$key] = [
                'old' => $resource['id'],
                'new' => $vendor->id,
            ];
        }

        Vendor::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $contact_repository = null;
        $client_repository = null;
    }


    private function processProducts(array $data): void
    {
        Product::unguard();

        $rules = [
            //'*.product_key' => 'required|distinct|unique:products,product_key,null,null,company_id,' . $this->company->id,
            '*.cost' => 'numeric',
            '*.price' => 'numeric',
            '*.quantity' => 'numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $product_repository = new ProductRepository();

        foreach ($data as $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            unset($modified['id']);

            $product_repository->save(
                $modified,
                ProductFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );
        }

        Product::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $product_repository = null;
    }

    private function processRecurringInvoices(array $data) :void
    {
        RecurringInvoice::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $invoice_repository = new InvoiceMigrationRepository();

        foreach ($data as $key => $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing invoices failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;
            $modified['line_items'] = $this->cleanItems($modified['line_items']);

            unset($modified['id']);

            if (array_key_exists('invitations', $resource)) {
                foreach ($resource['invitations'] as $key => $invite) {
                    $resource['invitations'][$key]['client_contact_id'] = $this->transformId('client_contacts', $invite['client_contact_id']);
                    $resource['invitations'][$key]['user_id'] = $modified['user_id'];
                    $resource['invitations'][$key]['company_id'] = $this->company->id;
                    unset($resource['invitations'][$key]['recurring_invoice_id']);
                }
            
                $modified['invitations'] = $this->deDuplicateInvitations($resource['invitations']);

            }
            
            $invoice = $invoice_repository->save(
                $modified,
                RecurringInvoiceFactory::create($this->company->id, $modified['user_id'])
            );

            $key = "recurring_invoices_{$resource['id']}";

            $this->ids['recurring_invoices'][$key] = [
                'old' => $resource['id'],
                'new' => $invoice->id,
            ];
        }

        RecurringInvoice::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $invoice_repository = null;
    }

    private function processInvoices(array $data): void
    {
        Invoice::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $invoice_repository = new InvoiceMigrationRepository();

        foreach ($data as $key => $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing invoices failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;
            $modified['line_items'] = $this->cleanItems($modified['line_items']);

            unset($modified['id']);
                
            if (array_key_exists('invitations', $resource)) {
                foreach ($resource['invitations'] as $key => $invite) {
                    $resource['invitations'][$key]['client_contact_id'] = $this->transformId('client_contacts', $invite['client_contact_id']);
                    $resource['invitations'][$key]['user_id'] = $modified['user_id'];
                    $resource['invitations'][$key]['company_id'] = $this->company->id;
                    unset($resource['invitations'][$key]['invoice_id']);
                }

                $modified['invitations'] = $this->deDuplicateInvitations($resource['invitations']);

            }

            $invoice = $invoice_repository->save(
                $modified,
                InvoiceFactory::create($this->company->id, $modified['user_id'])
            );

            $key = "invoices_{$resource['id']}";

            $this->ids['invoices'][$key] = [
                'old' => $resource['id'],
                'new' => $invoice->id,
            ];
        }

        Invoice::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $invoice_repository = null;
    }


    /* Prevent edge case where V4 has inserted multiple invitations for a resource for a client contact */
    private function deDuplicateInvitations($invitations)
    {        
        return  array_intersect_key($invitations, array_unique(array_column($invitations, 'client_contact_id')));
    }

    private function processCredits(array $data): void
    {
        Credit::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $credit_repository = new CreditRepository();

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing credits failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            unset($modified['id']);

            $credit = $credit_repository->save(
                $modified,
                CreditFactory::create($this->company->id, $modified['user_id'])
            );

            $key = "credits_{$resource['id']}";

            $this->ids['credits'][$key] = [
                'old' => $resource['id'],
                'new' => $credit->id,
            ];
        }

        Credit::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $credit_repository = null;

    }

    private function processQuotes(array $data): void
    {
        Quote::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $quote_repository = new QuoteRepository();

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing quotes failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);

            $modified['company_id'] = $this->company->id;

            unset($modified['id']);


            if (array_key_exists('invitations', $resource)) {
                foreach ($resource['invitations'] as $key => $invite) {
                    $resource['invitations'][$key]['client_contact_id'] = $this->transformId('client_contacts', $invite['client_contact_id']);
                    $resource['invitations'][$key]['user_id'] = $modified['user_id'];
                    $resource['invitations'][$key]['company_id'] = $this->company->id;
                    unset($resource['invitations'][$key]['invoice_id']);
                }

                $modified['invitations'] = $this->deDuplicateInvitations($resource['invitations']);

            }

            $quote = $quote_repository->save(
                $modified,
                QuoteFactory::create($this->company->id, $modified['user_id'])
            );

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "quotes_{$resource['id']}";

            $this->ids['quotes'][$key] = [
                'old' => $resource['id'],
                'new' => $quote->id,
            ];
        }

        Quote::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $quote_repository = null;
    }

    private function processPayments(array $data): void
    {
        Payment::reguard();

        $rules = [
            '*.amount' => ['required'],
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $payment_repository = new PaymentMigrationRepository(new CreditRepository());

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing payments failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            //$modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
            $modified['company_id'] = $this->company->id;

            //unset($modified['invoices']);
            unset($modified['invoice_id']);

            if (isset($modified['invoices'])) {
                foreach ($modified['invoices'] as $key => $invoice) {
                    if ($this->tryTransformingId('invoices', $invoice['invoice_id'])) {
                        $modified['invoices'][$key]['invoice_id'] = $this->transformId('invoices', $invoice['invoice_id']);
                    } else {
                        $modified['credits'][$key]['credit_id'] = $this->transformId('credits', $invoice['invoice_id']);
                        $modified['credits'][$key]['amount'] = $modified['invoices'][$key]['amount'];
                    }
                }
            }

            $payment = $payment_repository->save(
                $modified,
                PaymentFactory::create($this->company->id, $modified['user_id'])
            );

            if (array_key_exists('company_gateway_id', $resource) && isset($resource['company_gateway_id']) && $resource['company_gateway_id'] != 'NULL') {
                $payment->company_gateway_id = $this->transformId('company_gateways', $resource['company_gateway_id']);
                $payment->save();
            }
            

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['payments'] = [
                "payments_{$old_user_key}" => [
                    'old' => $old_user_key,
                    'new' => $payment->id,
                ],
            ];

        }

        Payment::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $payment_repository = null;
    }

    private function updatePaymentForStatus($payment, $status_id) :Payment
    {
        // define('PAYMENT_STATUS_PENDING', 1);
        // define('PAYMENT_STATUS_VOIDED', 2);
        // define('PAYMENT_STATUS_FAILED', 3);
        // define('PAYMENT_STATUS_COMPLETED', 4);
        // define('PAYMENT_STATUS_PARTIALLY_REFUNDED', 5);
        // define('PAYMENT_STATUS_REFUNDED', 6);
            
        switch ($status_id) {
            case 1:
                return $payment;
                break;
            case 2:
                return $payment->service()->deletePayment();
                break;
            case 3:
                return $payment->service()->deletePayment();
                break;
            case 4:
                return $payment;
                break;
            case 5:
                $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
                $payment->save();
                return $payment;
                break;
            case 6:
                $payment->status_id = Payment::STATUS_REFUNDED;
                $payment->save();
                return $payment;
                break;

            default:
                return $payment;
                break;
        }
    }

    private function processDocuments(array $data): void
    {
        // Document::unguard();
        /* No validators since data provided by database is already valid. */

        foreach ($data as $resource) {

            $modified = $resource;

            if (array_key_exists('invoice_id', $resource) && $resource['invoice_id'] && ! array_key_exists('invoices', $this->ids)) {
                throw new ResourceDependencyMissing('Processing documents failed, because of missing dependency - invoices.');
            }

            if (array_key_exists('expense_id', $resource) && $resource['expense_id'] && ! array_key_exists('expenses', $this->ids)) {
                throw new ResourceDependencyMissing('Processing documents failed, because of missing dependency - expenses.');
            }

            if (array_key_exists('invoice_id', $resource) && $resource['invoice_id'] && array_key_exists('invoices', $this->ids)) {
                $invoice_id = $this->transformId('invoices', $resource['invoice_id']);
                $entity = Invoice::where('id', $invoice_id)->withTrashed()->first();
            }

            if (array_key_exists('expense_id', $resource) && $resource['expense_id'] && array_key_exists('expenses', $this->ids)) {
                $expense_id = $this->transformId('expenses', $resource['expense_id']);
                $entity = Expense::where('id', $expense_id)->withTrashed()->first();
            }

            $file_url = $resource['url'];
            $file_name = $resource['name'];
            $file_path = sys_get_temp_dir().'/'.$file_name;

            try {
                file_put_contents($file_path, $this->curlGet($file_url));
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $file_info = $finfo->file($file_path);

                $uploaded_file = new UploadedFile(
                                $file_path,
                                $file_name,
                                $file_info,
                                filesize($file_path),
                                0,
                                false
                            );

                $this->saveDocument($uploaded_file, $entity, $is_public = true);
            }
            catch(\Exception $e) {

                //do nothing, gracefully :)
                
            }

        }

    }

    private function processPaymentTerms(array $data) :void
    {
        PaymentTerm::unguard();

        $modified = collect($data)->map(function ($item) {
            $item['user_id'] = $this->user->id;
            $item['company_id'] = $this->company->id;
            $item['is_deleted'] = isset($item['is_deleted']) ? $item['is_deleted'] : 0;
            
            return $item;
        })->toArray();

        PaymentTerm::insert($modified);

        PaymentTerm::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processCompanyGateways(array $data) :void
    {
        CompanyGateway::unguard();

        $rules = [
            '*.gateway_key' => 'required',
            '*.fees_and_limits' => new ValidCompanyGatewayFeesAndLimitsRule(),
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        foreach ($data as $resource) {
            $modified = $resource;

            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            unset($modified['id']);

            if (isset($modified['config'])) {
                $modified['config'] = encrypt($modified['config']);
            }

            if (isset($modified['fees_and_limits'])) {
                $modified['fees_and_limits'] = $this->cleanFeesAndLimits($modified['fees_and_limits']);
            }

            $company_gateway = CompanyGateway::create($modified);

            $key = "company_gateways_{$resource['id']}";

            $this->ids['company_gateways'][$key] = [
                    'old' => $resource['id'],
                    'new' => $company_gateway->id,
            ];
        }

        CompanyGateway::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processClientGatewayTokens(array $data) :void
    {
        ClientGatewayToken::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            //$modified['user_id'] = $this->processUserId($resource);

            $cgt = ClientGatewayToken::Create($modified);

            $key = "client_gateway_tokens_{$resource['id']}";

            $this->ids['client_gateway_tokens'][$key] = [
                'old' => $resource['id'],
                'new' => $cgt->id,
            ];
        }

        ClientGatewayToken::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processTaskStatuses(array $data) :void
    {
        info('in task statuses');
        TaskStatus::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            $task_status = TaskStatus::Create($modified);

            $key = "task_statuses_{$resource['id']}";

            $this->ids['task_statuses'][$key] = [
                'old' => $resource['id'],
                'new' => $task_status->id,
            ];
        }

        TaskStatus::reguard();

        $data = null;
        info('finished task statuses');
    }

    private function processExpenseCategories(array $data) :void
    {
        ExpenseCategory::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            $expense_category = ExpenseCategory::Create($modified);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "expense_categories_{$resource['id']}";

            $this->ids['expense_categories'][$key] = [
                'old' => $resource['id'],
                'new' => $expense_category->id,
            ];

            // $this->ids['expense_categories'] = [
            //     "expense_categories_{$old_user_key}" => [
            //         'old' => $resource['id'],
            //         'new' => $expense_category->id,
            //     ],
            // ];
        }
        
        ExpenseCategory::reguard();

        $data = null;
    }

    private function processTasks(array $data) :void
    {
        Task::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (isset($modified['client_id'])) {
                $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            }

            if (isset($modified['invoice_id'])) {
                $modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
            }
            
            if (isset($modified['project_id'])) {
                $modified['project_id'] = $this->transformId('projects', $resource['project_id']);
            }
            
            if (isset($modified['status_id'])) {
                $modified['status_id'] = $this->transformId('task_statuses', $resource['status_id']);
            }

            $task = Task::Create($modified);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['tasks'] = [
                "tasks_{$old_user_key}" => [
                    'old' => $resource['id'],
                    'new' => $task->id,
                ],
            ];
        }
        
        Task::reguard();

        $data = null;
    }

    private function processProjects(array $data) :void
    {
        Project::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (isset($modified['client_id'])) {
                $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            }

            $project = Project::Create($modified);

            $key = "projects_{$resource['id']}";

            $this->ids['projects'][$key] = [
                'old' => $resource['id'],
                'new' => $project->id,
            ];
        }

        Project::reguard();

        $data = null;
    }

    private function processExpenses(array $data) :void
    {
        Expense::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (isset($resource['client_id'])) {
                $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            }
            
            if (isset($resource['category_id'])) {
                $modified['category_id'] = $this->transformId('expense_categories', $resource['category_id']);
            }
            
            if (isset($resource['invoice_id'])) {
                $modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
            }

            if (isset($resource['project_id'])) {
                $modified['project_id'] = $this->transformId('projects', $resource['project_id']);
            }

            if (isset($resource['vendor_id'])) {
                $modified['vendor_id'] = $this->transformId('vendors', $resource['vendor_id']);
            }

            $expense = Expense::Create($modified);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "expenses_{$resource['id']}";

            $this->ids['expenses'][$key] = [
                'old' => $resource['id'],
                'new' => $expense->id,
            ];

        }

        Expense::reguard();

        $data = null;
    }
    /**
     * |--------------------------------------------------------------------------
     * | Additional migration methods.
     * |--------------------------------------------------------------------------
     * |
     * | These methods aren't initialized automatically, so they don't depend on
     * | the migration data.
     */

    /**
     * Cloned from App\Http\Requests\User\StoreUserRequest.
     *
     * @param string $data
     * @return User
     */
    public function fetchUser(string $data): User
    {
        $user = MultiDB::hasUser(['email' => $data]);

        if (! $user) {
            $user = UserFactory::create($this->company->account->id);
        }
        
        return $user;
    }

    /**
     * @param string $resource
     * @param string $old
     * @return int
     * @throws Exception
     */
    public function transformId($resource, string $old): int
    {
        if (! array_key_exists($resource, $this->ids)) {
            info(print_r($resource, 1));
            throw new Exception("Resource {$resource} not available.");
        }

        if (! array_key_exists("{$resource}_{$old}", $this->ids[$resource])) {
            throw new Exception("Missing resource key: {$resource}_{$old}");
        }

        return $this->ids[$resource]["{$resource}_{$old}"]['new'];
    }

    private function tryTransformingId($resource, string $old): ?int
    {
        if (! array_key_exists($resource, $this->ids)) {
            return false;
        }

        if (! array_key_exists("{$resource}_{$old}", $this->ids[$resource])) {
            return false;
        }

        return $this->ids[$resource]["{$resource}_{$old}"]['new'];
    }

    /**
     * Process & handle user_id.
     *
     * @param array $resource
     * @return int|mixed
     * @throws Exception
     */
    public function processUserId(array $resource)
    {
        if (! array_key_exists('user_id', $resource)) {
            return $this->user->id;
        }

        if (array_key_exists('user_id', $resource) && ! array_key_exists('users', $this->ids)) {
            return $this->user->id;
        }

        return $this->transformId('users', $resource['user_id']);
    }

    public function failed($exception = null)
    {
        info('the job failed');

        $job_failure = new MigrationFailure();
        $job_failure->string_metric5 = get_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->batch();

        info(print_r($exception->getMessage(), 1));
    }


    public function curlGet($url, $headers = false)
    {

        return $this->exec('GET', $url, null);
    }

    public function exec($method, $url, $data)
    {
        nlog($this->token);

        $client =  new \GuzzleHttp\Client(['headers' => 
            [ 
            'X-Ninja-Token' => $this->token,        
            ]
        ]);

        $response = $client->request('GET', $url);

        return $response->getBody();
    }


    /* In V4 we use negative invoices (credits) and add then into the client balance. In V5, these sit off ledger and are applied later.
     This next section will check for credit balances and reduce the client balance so that the V5 balances are correct
    */
    private function fixClientBalances()
    {
       
        Client::cursor()->each(function ($client) {

            $credit_balance = $client->credits->where('is_deleted', false)->sum('balance');

            if($credit_balance > 0){
                $client->balance += $credit_balance;
                $client->save();
            }

        });

    }
}
