<?php

namespace App\Console\Commands;

use App\DataMapper\DefaultSettings;
use App\Events\Invoice\InvoiceWasMarkedSent;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Jobs\Invoice\UpdateInvoicePayment;
use App\Listeners\Invoice\CreateInvoiceInvitation;
use App\Models\CompanyToken;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\User;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CreateTestData extends Command
{
    use MakesHash;
    /**
     * @var string
     */
    protected $description = 'Create Test Data';
    /**
     * @var string
     */
    protected $signature = 'ninja:create-test-data {count=1}';

    protected $invoice_repo;

    /**
     * Create a new command instance.
     *
     * @return void
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
        $this->info(date('r').' Running CreateTestData...');
        $this->count = $this->argument('count');

        $this->info('Warming up cache');

        $this->warmCache();

        $this->createSmallAccount();
        $this->createMediumAccount();
        $this->createLargeAccount();

    }


    private function createSmallAccount()
    {

        $this->info('Creating Small Account and Company');

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
            'domain' => 'ninja.test:8000',
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::whereEmail('small@example.com')->first();

        if(!$user)
        {    
            $user = factory(\App\Models\User::class)->create([
            //    'account_id' => $account->id,
                'email' => 'small@example.com',
                'confirmation_code' => $this->createDbHash(config('database.default'))
            ]);
        }
        
        $token = \Illuminate\Support\Str::random(64);

        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => $token,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'settings' => json_encode(DefaultSettings::userSettings()),
        ]);


        $this->info('Creating '.$this->count. ' clients');


        for($x=0; $x<$this->count; $x++) {
            $z = $x+1;
            $this->info("Creating client # ".$z);

                $this->createClient($company, $user);
        }

    }

    private function createMediumAccount()
    {
        $this->info('Creating Medium Account and Company');

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
            'domain' => 'ninja.test:8000',
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::whereEmail('medium@example.com')->first();

        if(!$user)
        {    
            $user = factory(\App\Models\User::class)->create([
            //    'account_id' => $account->id,
                'email' => 'medium@example.com',
                'confirmation_code' => $this->createDbHash(config('database.default'))
            ]);
        }
        
        $token = \Illuminate\Support\Str::random(64);

        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => $token,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'settings' => json_encode(DefaultSettings::userSettings()),
        ]);

        $this->count = $this->count*10;

        $this->info('Creating '.$this->count. ' clients');


        for($x=0; $x<$this->count; $x++) {
            $z = $x+1;
            $this->info("Creating client # ".$z);

                $this->createClient($company, $user);
        }

    }

    private function createLargeAccount()
    {
       $this->info('Creating Large Account and Company');

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
            'domain' => 'ninja.test:8000',
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::whereEmail('large@example.com')->first();

        if(!$user)
        {    
            $user = factory(\App\Models\User::class)->create([
            //    'account_id' => $account->id,
                'email' => 'large@example.com',
                'confirmation_code' => $this->createDbHash(config('database.default'))
            ]);
        }
        
        $token = \Illuminate\Support\Str::random(64);

        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => $token,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'settings' => json_encode(DefaultSettings::userSettings()),
        ]);

        $this->count = $this->count*100;

        $this->info('Creating '.$this->count. ' clients');


        for($x=0; $x<$this->count; $x++) {
            $z = $x+1;
            $this->info("Creating client # ".$z);

                $this->createClient($company, $user);
        }

    }

    private function createClient($company, $user)
    {
        $client = factory(\App\Models\Client::class)->create([
            'user_id' => $user->id,
            'company_id' => $company->id
        ]);


            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,rand(1,50))->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id
            ]);

        $y = $this->count * rand(1,5);

        $this->info("Creating {$y} invoices");

        for($x=0; $x<$y; $x++){

            $this->createInvoice($client);
        }
    }

    private function createInvoice($client)
    {
        $faker = \Faker\Factory::create();

        $invoice = InvoiceFactory::create($client->company->id,$client->user->id);//stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->date = $faker->date();
        
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_Taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        $invoice->save();

            event(new CreateInvoiceInvitation($invoice));
            
            UpdateCompanyLedgerWithInvoice::dispatchNow($invoice, $invoice->balance);

            $this->invoice_repo->markSent($invoice);

            event(new InvoiceWasMarkedSent($invoice));

            if(rand(0, 1)) {

                $payment = PaymentFactory::create($client->company->id, $client->user->id);
                $payment->payment_date = now();
                $payment->client_id = $client->id;
                $payment->amount = $invoice->balance;
                $payment->transaction_reference = rand(0,500);
                $payment->payment_type_id = PaymentType::CREDIT_CARD_OTHER;
                $payment->status_id = Payment::STATUS_COMPLETED;
                $payment->save();

                $payment->invoices()->save($invoice);

                event(new PaymentWasCreated($payment));

                UpdateInvoicePayment::dispatchNow($payment);
            }
    }

    private function buildLineItems()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost =10;

        $line_items[] = $item;

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
