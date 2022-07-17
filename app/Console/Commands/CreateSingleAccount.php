<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\RecurringInvoice\RecurringInvoiceWasCreated;
use App\Factory\GroupSettingFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\SubscriptionFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Repositories\InvoiceRepository;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

class CreateSingleAccount extends Command
{
    use MakesHash, GeneratesCounter;
    
    protected $description = 'Create Single Sample Account';
    
    protected $signature = 'ninja:create-single-account {gateway=all} {--database=db-ninja-01}';

    protected $invoice_repo;

    protected $count;

    protected $gateway;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        if(config('ninja.is_docker'))
            return;
        
        if (!$this->confirm('Are you sure you want to inject dummy data?'))
            return;

        $this->invoice_repo = new InvoiceRepository();

        MultiDB::setDb($this->option('database'));

        $this->info(date('r').' Create Single Sample Account...');
        $this->count = 1;
        $this->gateway = $this->argument('gateway');

        $this->info('Warming up cache');

        $this->warmCache();

        $this->createSmallAccount();
    }

    private function createSmallAccount()
    {
        $this->info('Creating Small Account and Company');

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'slack_webhook_url' => config('ninja.notification.slack'),
            'default_password_timeout' => 30*60000,
            'portal_mode' => 'domain',
            'portal_domain' => 'http://ninja.test:8000',
            'track_inventory' => true
        ]);

        $settings = $company->settings;
        $settings->invoice_terms = 'Default company invoice terms';
        $settings->quote_terms = 'Default company quote terms';
        $settings->invoice_footer = 'Default invoice footer';

        $company->settings = $settings;
        $company->client_registration_fields = ClientRegistrationFields::generate();
        $company->save();

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::whereEmail('small@example.com')->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $account->id,
                'email' => 'small@example.com',
                'confirmation_code' => $this->createDbHash(config('database.default')),
            ]);
        }

        $company_token = new CompanyToken;
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = 'company-token-test';
        $company_token->is_system = true;

        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        Product::factory()->count(1)->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);


        TaxRate::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => 'GST',
            'rate' => 10
        ]);

        TaxRate::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => 'VAT',
            'rate' => 17.5
        ]);

        TaxRate::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => 'CA Sales Tax',
            'rate' => 5
        ]);


        $this->info('Creating '.$this->count.' clients');

        for ($x = 0; $x < $this->count; $x++) {
            $z = $x + 1;
            $this->info('Creating client # '.$z);

            $this->createClient($company, $user);
        }

        CreateCompanyTaskStatuses::dispatchNow($company, $user);

        for ($x = 0; $x < $this->count; $x++) {
            $client = $company->clients->random();

            $this->info('creating invoice for client #'.$client->id);
            $this->createInvoice($client);
            $this->info('creating invoice for client #'.$client->id);
            $this->createInvoice($client);
            $this->info('creating invoice for client #'.$client->id);
            $this->createInvoice($client);
            $this->info('creating invoice for client #'.$client->id);
            $this->createInvoice($client);
            $this->info('creating invoice for client #'.$client->id);
            $this->createInvoice($client);

            $client = $company->clients->random();

            // $this->info('creating credit for client #'.$client->id);
            // $this->createCredit($client); /** Prevents Stripe from running payments. */

            $client = $company->clients->random();

            $this->info('creating quote for client #'.$client->id);
            $this->createQuote($client);

            $client = $company->clients->random();

            $this->info('creating expense for client #'.$client->id);
            $this->createExpense($client);

            $client = $company->clients->random();

            $this->info('creating vendor for client #'.$client->id);
            $this->createVendor($client);

            $client = $company->clients->random();

            $this->info('creating task for client #' . $client->id);
            $this->createTask($client);

            $client = $company->clients->random();

            $this->info('creating project for client #' . $client->id);
            $this->createProject($client);

            $this->info('creating credit for client #' . $client->id);
            $this->createCredit($client);

            $this->info('creating recurring invoice for client # ' . $client->id);
            $this->createRecurringInvoice($client);
        }

        $this->createGateways($company, $user);

        $this->createSubsData($company, $user);
    }

    private function createSubsData($company, $user)
    {
        $gs = GroupSettingFactory::create($company->id, $user->id);
        $gs->name = "plans";
        $gs->save();

        $p1 = Product::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'product_key' => 'pro_plan',
            'notes' => 'The Pro Plan',
            'cost' => 10,
            'price' => 10,
            'quantity' => 1,
        ]);

        $p2 = Product::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'product_key' => 'enterprise_plan',
            'notes' => 'The Enterprise Plan',
            'cost' => 14,
            'price' => 14,
            'quantity' => 1,
        ]);

        $p3 = Product::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'product_key' => 'free_plan',
            'notes' => 'The Free Plan',
            'cost' => 0,
            'price' => 0,
            'quantity' => 1,
        ]);

        $webhook_config = [
            'post_purchase_url' => 'http://ninja.test:8000/api/admin/plan',
            'post_purchase_rest_method' => 'POST',
            'post_purchase_headers' => [],
        ];

        $sub = SubscriptionFactory::create($company->id, $user->id);
        $sub->name = "Pro Plan";
        $sub->group_id = $gs->id;
        $sub->recurring_product_ids = "{$p1->hashed_id}";
        $sub->webhook_configuration = $webhook_config;
        $sub->allow_plan_changes = true;
        $sub->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $sub->save();

        $sub = SubscriptionFactory::create($company->id, $user->id);
        $sub->name = "Enterprise Plan";
        $sub->group_id = $gs->id;
        $sub->recurring_product_ids = "{$p2->hashed_id}";
        $sub->webhook_configuration = $webhook_config;
        $sub->allow_plan_changes = true;
        $sub->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $sub->save();

        $sub = SubscriptionFactory::create($company->id, $user->id);
        $sub->name = "Free Plan";
        $sub->group_id = $gs->id;
        $sub->recurring_product_ids = "{$p3->hashed_id}";
        $sub->webhook_configuration = $webhook_config;
        $sub->allow_plan_changes = true;
        $sub->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $sub->save();
    }

    private function createClient($company, $user)
    {

        // dispatch(function () use ($company, $user) {

        // });
        $client = Client::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);

        ClientContact::factory()->create([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'company_id' => $company->id,
                    'is_primary' => 1,
                    'email' => 'user@example.com'
                ]);

        ClientContact::factory()->count(rand(1, 2))->create([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'company_id' => $company->id,
                ]);

        $client->number = $this->getNextClientNumber($client);

        $settings = $client->settings;
        $settings->currency_id = "1";
//        $settings->use_credits_payment = "always";

        $client->settings = $settings;

        $country = Country::all()->random();

        $client->country_id = $country->id;
        $client->save();
    }

    private function createExpense($client)
    {
        Expense::factory()->count(rand(1, 2))->create([
                'user_id' => $client->user->id,
                'client_id' => $client->id,
                'company_id' => $client->company->id,
            ]);
    }

    private function createVendor($client)
    {
        $vendor = Vendor::factory()->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company->id,
            ]);

        VendorContact::factory()->create([
                'user_id' => $client->user->id,
                'vendor_id' => $vendor->id,
                'company_id' => $client->company->id,
                'is_primary' => 1,
            ]);

        VendorContact::factory()->count(rand(1, 2))->create([
                'user_id' => $client->user->id,
                'vendor_id' => $vendor->id,
                'company_id' => $client->company->id,
                'is_primary' => 0,
            ]);
    }

    private function createTask($client)
    {
        $vendor = Task::factory()->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company->id,
            ]);
    }

    private function createProject($client)
    {
        $vendor = Project::factory()->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company->id,
                'client_id' => $client->id,
            ]);
    }

    private function createInvoice($client)
    {
        $faker = Factory::create();

        $invoice = InvoiceFactory::create($client->company->id, $client->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $dateable = Carbon::now()->subDays(rand(0, 90));
        $invoice->date = $dateable;

        $invoice->line_items = $this->buildLineItems(rand(1, 10));
        $invoice->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $invoice->tax_name1 = 'GST';
            $invoice->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $invoice->tax_name2 = 'VAT';
            $invoice->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $invoice->tax_name3 = 'CA Sales Tax';
            $invoice->tax_rate3 = 5;
        }

        $invoice->custom_value1 = $faker->date;
        $invoice->custom_value2 = rand(0, 1) ? 'yes' : 'no';

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        if ($this->gateway === 'braintree') {
            $invoice->amount = 100; // Braintree sandbox only allows payments under 2,000 to complete successfully.
        }

        $invoice->save();
        $invoice->service()->createInvitations()->markSent();

        $this->invoice_repo->markSent($invoice);

        event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars()));
    }

    private function createCredit($client)
    {
        $faker = Factory::create();

        $credit = Credit::factory()->create(['user_id' => $client->user->id, 'company_id' => $client->company->id, 'client_id' => $client->id]);

        $dateable = Carbon::now()->subDays(rand(0, 90));
        $credit->date = $dateable;

        $credit->line_items = $this->buildCreditItem();
        $credit->uses_inclusive_taxes = false;

        $credit->save();

        $invoice_calc = new InvoiceSum($credit);
        $invoice_calc->build();

        $credit = $invoice_calc->getCredit();

        $credit->save();
        $credit->service()->markSent()->save();
        $credit->service()->createInvitations();
    }

    private function createQuote($client)
    {
        $faker = Factory::create();

        $quote = Quote::factory()->create(['user_id' => $client->user->id, 'company_id' => $client->company->id, 'client_id' => $client->id]);
        $quote->date = $faker->date();
        $quote->client_id = $client->id;

        $quote->setRelation('client', $client);

        $quote->line_items = $this->buildLineItems(rand(1, 10));
        $quote->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $quote->tax_name1 = 'GST';
            $quote->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $quote->tax_name2 = 'VAT';
            $quote->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $quote->tax_name3 = 'CA Sales Tax';
            $quote->tax_rate3 = 5;
        }

        $quote->save();

        $quote_calc = new InvoiceSum($quote);
        $quote_calc->build();

        $quote = $quote_calc->getQuote();

        $quote->save();

        $quote->service()->markSent()->save();
        $quote->service()->createInvitations();
    }


    private function buildCreditItem()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1000;

        $product = Product::all()->random();

        $item->cost = (float) $product->cost;
        $item->product_key = $product->product_key;
        $item->notes = $product->notes;
        $item->custom_value1 = $product->custom_value1;
        $item->custom_value2 = $product->custom_value2;
        $item->custom_value3 = $product->custom_value3;
        $item->custom_value4 = $product->custom_value4;

        $line_items[] = $item;


        return $line_items;
    }


    private function buildLineItems($count = 1)
    {
        $line_items = [];

        for ($x = 0; $x < $count; $x++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            //$item->cost = 10;

            if (rand(0, 1)) {
                $item->tax_name1 = 'GST';
                $item->tax_rate1 = 10.00;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'VAT';
                $item->tax_rate1 = 17.50;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'Sales Tax';
                $item->tax_rate1 = 5;
            }

            $product = Product::all()->random();

            $item->cost = (float) $product->cost;
            $item->product_key = $product->product_key;
            $item->notes = $product->notes;
            $item->custom_value1 = $product->custom_value1;
            $item->custom_value2 = $product->custom_value2;
            $item->custom_value3 = $product->custom_value3;
            $item->custom_value4 = $product->custom_value4;

            $line_items[] = $item;
        }

        return $line_items;
    }

    private function warmCache()
    {
        /* Warm up the cache !*/
        $cached_tables = config('ninja.cached_tables');

        foreach ($cached_tables as $name => $class) {
            if (! Cache::has($name)) {
                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count()) {
                    Cache::forever($name, $tableData);
                }
            }
        }
    }

    private function createGateways($company, $user)
    {
        if (config('ninja.testvars.stripe') && ($this->gateway == 'all' || $this->gateway == 'stripe')) {

            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();

            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();


        }

        if (config('ninja.testvars.paypal') && ($this->gateway == 'all' || $this->gateway == 'paypal')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '38f2c48af60c7dd69e04248cbb24c36e';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.paypal'));
            $cg->save();

            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }

        if (config('ninja.testvars.checkout') && ($this->gateway == 'all' || $this->gateway == 'checkout')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '3758e7f7c6f4cecf0f4f348b9a00f456';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.checkout'));
            $cg->save();

            $gateway_types = $cg->driver(new Client)->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }

        if (config('ninja.testvars.authorize') && ($this->gateway == 'all' || $this->gateway == 'authorizenet')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '3b6621f970ab18887c4f6dca78d3f8bb';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.authorize'));
            $cg->save();

            $gateway_types = $cg->driver(new Client)->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }

        if (config('ninja.testvars.wepay') && ($this->gateway == 'all' || $this->gateway == 'wepay')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '8fdeed552015b3c7b44ed6c8ebd9e992';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.wepay'));
            $cg->save();

            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }

        if (config('ninja.testvars.braintree') && ($this->gateway == 'all' || $this->gateway == 'braintree')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = 'f7ec488676d310683fb51802d076d713';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.braintree'));
            $cg->save();

            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }


        if (config('ninja.testvars.paytrace.decrypted') && ($this->gateway == 'all' || $this->gateway == 'paytrace')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = 'bbd736b3254b0aabed6ad7fda1298c88';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.paytrace.decrypted'));

            $cg->save();


            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }

        if (config('ninja.testvars.mollie') && ($this->gateway == 'all' || $this->gateway == 'mollie')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '1bd651fb213ca0c9d66ae3c336dc77e8';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.mollie'));
            $cg->save();

            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }

        if (config('ninja.testvars.square') && ($this->gateway == 'all' || $this->gateway == 'square')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '65faab2ab6e3223dbe848b1686490baz';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.square'));
            $cg->save();

            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }
    }

    private function createRecurringInvoice($client)
    {
        $faker = Factory::create();

        $invoice = RecurringInvoiceFactory::create($client->company->id, $client->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $dateable = Carbon::now()->subDays(rand(0, 90));
        $invoice->date = $dateable;

        $invoice->line_items = $this->buildLineItems(rand(1, 10));
        $invoice->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $invoice->tax_name1 = 'GST';
            $invoice->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $invoice->tax_name2 = 'VAT';
            $invoice->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $invoice->tax_name3 = 'CA Sales Tax';
            $invoice->tax_rate3 = 5;
        }

        $invoice->custom_value1 = $faker->date;
        $invoice->custom_value2 = rand(0, 1) ? 'yes' : 'no';

        $invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        $invoice->save();

        event(new RecurringInvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars()));
    }
}
