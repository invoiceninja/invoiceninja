<?php

namespace Tests\Integration;

use App\DataMapper\CompanySettings;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\CompanyUserFactory;
use App\Jobs\Invoice\MarkInvoicePaid;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\MockAccountData;
use Tests\TestCase;

/** @test*/

class CompanyLedgerTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;

    public $company;

    public $client;

    public $user;

    public $token;

    public $account;

    public function setUp() :void
    {
        parent::setUp();

        /* Warm up the cache !*/
        $cached_tables = config('ninja.cached_tables');
        
        foreach ($cached_tables as $name => $class) {
            
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

        $this->account = factory(\App\Models\Account::class)->create();
        $this->company = factory(\App\Models\Company::class)->create([
            'account_id' => $this->account->id,
        ]);

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png';
        $settings->website      = 'www.invoiceninja.com';
        $settings->address1     = 'Address 1';
        $settings->address2     = 'Address 2';
        $settings->city         = 'City';
        $settings->state        = 'State';
        $settings->postal_code  = 'Postal Code';
        $settings->phone        = '555-343-2323';
        $settings->email        = 'user@example.com';
        $settings->country_id   = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number  = 'id number';

        $this->company->settings = $settings;
        $this->company->save();

        $this->account->default_company_id = $this->company->id;
        $this->account->save();

        $this->user = User::whereEmail('user@example.com')->first();

        if(!$this->user){
            $this->user = factory(\App\Models\User::class)->create([
                'password' => Hash::make('ALongAndBriliantPassword'),
                'confirmation_code' => $this->createDbHash(config('database.default'))
            ]);
        }
        
        $cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = CompanyToken::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'name' => 'test token',
            'token' => $this->token,
        ]);

            $this->client = factory(\App\Models\Client::class)->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
            ]);


            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

            
    }

    public function testBaseLine()
    {

        $this->assertEquals($this->company->invoices->count(), 0);
        $this->assertEquals($this->company->clients->count(), 1);
        $this->assertEquals($this->client->balance, 0);
    }

}