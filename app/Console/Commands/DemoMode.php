<?php

namespace App\Console\Commands;

use App\DataMapper\CompanySettings;
use App\Events\Invoice\InvoiceWasCreated;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Ninja\CompanySizeCheck;
use App\Jobs\Util\VersionCheck;
use App\Models\CompanyToken;
use App\Models\Country;
use App\Models\Product;
use App\Models\User;
use App\Repositories\InvoiceRepository;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Composer\Composer;
use Composer\Console\Application;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArrayInput;

class DemoMode extends Command
{
    use MakesHash, GeneratesCounter;

    private $count;

    protected $name = 'ninja:demo-mode';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:demo-mode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup demo mode';

    protected $invoice_repo;
    
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
        set_time_limit(0);

        $this->info("Migrating");
        Artisan::call('migrate:fresh --force');

        $this->info("Seeding");
        Artisan::call('db:seed --force');

        $this->info("Seeding Random Data");
        $this->createSmallAccount();
        
        VersionCheck::dispatchNow();
        
        CompanySizeCheck::dispatchNow();

    }




    private function createSmallAccount()
    {
        $faker = \Faker\Factory::create();

        $this->count = 10;

        $this->info('Creating Small Account and Company');

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
            'slack_webhook_url' => config('ninja.notification.slack'),
            'enabled_modules' => 4095,
        ]);

         $settings = $company->settings;

        $settings->name = $faker->company;
        $settings->address1 = $faker->buildingNumber;
        $settings->address2 = $faker->streetAddress;
        $settings->city = $faker->city;
        $settings->state = $faker->state;
        $settings->postal_code = $faker->postcode;
        $settings->website = $faker->url;
        $settings->vat_number = (string)$faker->numberBetween(123456789, 987654321);
        $settings->phone = (string)$faker->phoneNumber;

         $company->settings = $settings;
         $company->save();

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::whereEmail('small@example.com')->first();

        if (!$user) {
            $user = factory(\App\Models\User::class)->create([
                'account_id' => $account->id,
                'email' => 'small@example.com',
                'confirmation_code' => $this->createDbHash(config('database.default'))
            ]);
        }
        
        $company_token = new CompanyToken;
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = Str::random(64);
        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
           // 'permissions' => '',
            'settings' => null,
        ]);

        $u2 = User::where('email', 'demo@invoiceninja.com')->first();

        if(!$u2){

            $u2 = factory(\App\Models\User::class)->create([
                'email'             => 'demo@invoiceninja.com',
                'password'          => Hash::make('demo'),
                'account_id' => $account->id,
                'confirmation_code' => $this->createDbHash(config('database.default'))
            ]);

            $company_token = new CompanyToken;
            $company_token->user_id = $u2->id;
            $company_token->company_id = $company->id;
            $company_token->account_id = $account->id;
            $company_token->name = 'test token';
            $company_token->token = 'TOKEN';
            $company_token->save();

            $u2->companies()->attach($company->id, [
                'account_id' => $account->id,
                'is_owner' => 1,
                'is_admin' => 1,
                'is_locked' => 0,
                'notifications' => CompanySettings::notificationDefaults(),
                'permissions' => '',
                'settings' => null,
            ]);
        }

        factory(\App\Models\Product::class, 50)->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);

        $this->info('Creating '.$this->count. ' clients');

        for ($x=0; $x<$this->count; $x++) {
            $z = $x+1;
            $this->info("Creating client # ".$z);

            if(rand(0,1))
                $this->createClient($company, $user);
            else
                $this->createClient($company, $u2);
        }

        for($x=0; $x<$this->count; $x++)
        {
            $client = $company->clients->random();

            $this->info('creating invoice for client #'.$client->id);

            for($y=0; $y<($this->count); $y++){
                $this->info("creating invoice #{$y} for client #".$client->id);
                $this->createInvoice($client);
            }

            $client = $company->clients->random();

            for($y=0; $y<($this->count); $y++){
                $this->info("creating credit #{$y} for client #".$client->id);
                $this->createCredit($client);
            }

            $client = $company->clients->random();

            for($y=0; $y<($this->count); $y++){
                $this->info("creating quote #{$y}  for client #".$client->id);
                $this->createQuote($client);
            }

            $client = $company->clients->random();

            $this->info("creating expense for client #".$client->id);
            $this->createExpense($client);

            $client = $company->clients->random();

            $this->info("creating vendor for client #".$client->id);
            $this->createVendor($client);

            $client = $company->clients->random();

            $this->info("creating task for client #".$client->id);
            $this->createTask($client);

            $client = $company->clients->random();

            $this->info("creating project for client #".$client->id);
            $this->createProject($client);
        }

    }

    private function createClient($company, $user)
    {

        // dispatch(function () use ($company, $user) {
   
        // });
        $client = factory(\App\Models\Client::class)->create([
                'user_id' => $user->id,
                'company_id' => $company->id
            ]);

        factory(\App\Models\ClientContact::class, 1)->create([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'company_id' => $company->id,
                    'is_primary' => 1
                ]);

        factory(\App\Models\ClientContact::class, rand(1, 5))->create([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'company_id' => $company->id
                ]);

        $client->id_number = $this->getNextClientNumber($client);

        $settings = $client->settings;
        $settings->currency_id = (string)rand(1,3);
        $client->settings = $settings;

        $client->country_id = array_rand([36,392,840,124,276,826]);
        $client->save();

    }

    private function createExpense($client)
    {
        factory(\App\Models\Expense::class, rand(1, 5))->create([
                'user_id' => $client->user_id,
                'client_id' => $client->id,
                'company_id' => $client->company_id
            ]);
    }

    private function createVendor($client)
    {
        $vendor = factory(\App\Models\Vendor::class)->create([
                'user_id' => $client->user_id,
                'company_id' => $client->company_id
            ]);


        factory(\App\Models\VendorContact::class, 1)->create([
                'user_id' => $client->user->id,
                'vendor_id' => $vendor->id,
                'company_id' => $client->company_id,
                'is_primary' => 1
            ]);

        factory(\App\Models\VendorContact::class, rand(1, 5))->create([
                'user_id' => $client->user->id,
                'vendor_id' => $vendor->id,
                'company_id' => $client->company_id,
                'is_primary' => 0
            ]);
    }

    private function createTask($client)
    {
        $vendor = factory(\App\Models\Task::class)->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company_id
            ]);
    }

    private function createProject($client)
    {
        $vendor = factory(\App\Models\Project::class)->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company_id
            ]);
    }

    private function createInvoice($client)
    {
        // for($x=0; $x<$this->count; $x++){
        //     dispatch(new CreateTestInvoiceJob($client));
        // }

        $faker = \Faker\Factory::create();

        $invoice = InvoiceFactory::create($client->company->id, $client->user->id);//stub the company and user_id
        $invoice->client_id = $client->id;

        if((bool)rand(0,1))
            $dateable = Carbon::now()->subDays(rand(0, 90));
        else
            $dateable = Carbon::now()->addDays(rand(0, 90));

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

       // $invoice->custom_value1 = $faker->date;
       // $invoice->custom_value2 = rand(0, 1) ? 'yes' : 'no';

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        $invoice->save();
        $invoice->service()->createInvitations()->markSent();

        $this->invoice_repo->markSent($invoice);

        if ((bool)rand(0, 2)) {

            $invoice = $invoice->service()->markPaid()->save();

            $invoice->payments->each(function ($payment){
                $payment->date = now()->addDays(rand(0,90));
                $payment->save();
            });
        }
        //@todo this slow things down, but gives us PDFs of the invoices for inspection whilst debugging.
        event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars()));
    }

    private function createCredit($client)
    {
        // for($x=0; $x<$this->count; $x++){

        //     dispatch(new CreateTestCreditJob($client));

        // }
        $faker = \Faker\Factory::create();

        $credit = factory(\App\Models\Credit::class)->create(['user_id' => $client->user->id, 'company_id' => $client->company->id, 'client_id' => $client->id]);

        if((bool)rand(0,1))
            $dateable = Carbon::now()->subDays(rand(0, 90));
        else
            $dateable = Carbon::now()->addDays(rand(0, 90));

        $credit->date = $dateable;

        $credit->line_items = $this->buildLineItems(rand(1, 10));
        $credit->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $credit->tax_name1 = 'GST';
            $credit->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $credit->tax_name2 = 'VAT';
            $credit->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $credit->tax_name3 = 'CA Sales Tax';
            $credit->tax_rate3 = 5;
        }

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

        $faker = \Faker\Factory::create();

        $quote =factory(\App\Models\Quote::class)->create(['user_id' => $client->user->id, 'company_id' => $client->company_id, 'client_id' => $client->id]);

        if((bool)rand(0,1))
            $dateable = Carbon::now()->subDays(rand(1, 30));
        else
            $dateable = Carbon::now()->addDays(rand(1, 30));

        $quote->date = $dateable;
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

    private function buildLineItems($count = 1)
    {
        $line_items = [];

        for ($x=0; $x<$count; $x++) {
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

            $item->cost = (float)$product->cost;
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
}
