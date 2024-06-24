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

namespace App\Console\Commands;

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\CompanySettings;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAccount extends Command
{
    use MakesHash;
    use GeneratesCounter;

    /**
     * @var string
     */
    protected $description = 'Create Single Account';

    /**
     * @var string
     */
    protected $signature = 'ninja:create-account {--email=} {--password=}';

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

        $this->createAccount();
    }

    private function createAccount()
    {
        $settings = CompanySettings::defaults();

        $settings->name = "Untitled Company";
        $settings->currency_id = '1';
        $settings->language_id = '1';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'portal_domain' => config('ninja.app_url'),
            'portal_mode' => 'domain',
            'settings' => $settings,
        ]);

        $company->client_registration_fields = ClientRegistrationFields::generate();
        $company->save();

        $account->default_company_id = $company->id;
        $account->set_react_as_default_ap = true;
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

        $company_token = new CompanyToken();
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

        (new CreateCompanyPaymentTerms($company, $user))->handle();
        (new CreateCompanyTaskStatuses($company, $user))->handle();
        (new VersionCheck())->handle();

    }

}
