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

namespace App\Console\Commands;

use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;
use App\Events\Invoice\InvoiceWasCreated;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\CreateCompanyTaskStatuses;
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
use App\Models\Task;
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

class CreateSingleAccount extends Command
{
    use MakesHash, GeneratesCounter;
    /**
     * @var string
     */
    protected $description = 'Create Single Sample Account';
    /**
     * @var string
     */
    protected $signature = 'ninja:create-single-account {gateway=all}';

    protected $invoice_repo;

    protected $count;

    protected $gateway;

    /**
     * Create a new command instance.
     *
     * @param InvoiceRepository $invoice_repo
     */
    public function __construct(InvoiceRepository $invoice_repo)
    {
        parent::__construct();

        $this->invoice_repo = $invoice_repo;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
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
        ]);

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

            $this->info('creating task for client #'.$client->id);
            $this->createTask($client);

            $client = $company->clients->random();

            $this->info('creating project for client #'.$client->id);
            $this->createProject($client);

            $this->info('creating credit for client #'.$client->id);
            $this->createCredit($client);
        }

        $this->createGateways($company, $user);
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

            $gateway_types = $cg->driver(new Client)->gatewayTypes();

            $fees_and_limits = new \stdClass;
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

            $gateway_types = $cg->driver(new Client)->gatewayTypes();

            $fees_and_limits = new \stdClass;
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

            $fees_and_limits = new \stdClass;
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

            $fees_and_limits = new \stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();
        }
    }
}
