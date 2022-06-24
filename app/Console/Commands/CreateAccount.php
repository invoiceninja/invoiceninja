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

use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;
use App\Events\Invoice\InvoiceWasCreated;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Jobs\Util\VersionCheck;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateAccount extends Command
{
    use MakesHash, GeneratesCounter;

    /**
     * @var string
     */
    protected $description = 'Create Single Account';

    /**
     * @var string
     */
    protected $signature = 'ninja:create-account {--email=} {--password=}';

    /**
     * Create a new command instance.
     *
     * @param InvoiceRepository $invoice_repo
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info(date('r').' Create Single Account...');

        $this->warmCache();

        $this->createAccount();
    }

    private function createAccount()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'portal_domain' => config('ninja.app_url'),
            'portal_mode' => 'domain',
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $email = $this->option('email') ?? 'admin@example.com';
        $password = $this->option('password') ?? 'changeme!';

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $email,
            'password' => Hash::make($password),
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email_verified_at' => now(),
            'first_name'        => 'New',
            'last_name'         => 'User',
            'phone'             => '',
        ]);

        $company_token = new CompanyToken;
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'User Token';
        $company_token->token = Str::random(64);
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

        CreateCompanyPaymentTerms::dispatchSync($company, $user);
        CreateCompanyTaskStatuses::dispatchSync($company, $user);
        VersionCheck::dispatchSync();
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
