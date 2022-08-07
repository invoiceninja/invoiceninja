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
use App\DataMapper\DefaultSettings;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Jobs\Invoice\CreateEntityPdf;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Migration\MaxCompanies;
use App\Mail\TemplateEmail;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:send-test-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends Test Emails to check templates';

    /**
     * Create a new command instance.
     *
     * @return void
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
        $faker = Factory::create();

        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => '123',
            'email' => $faker->safeEmail(),
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationDefaults(),
            //'settings' => DefaultSettings::userSettings(),
            'settings' => null,
        ]);

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new MaxCompanies($user->account->companies()->first());
        $nmo->company = $user->account->companies()->first();
        $nmo->settings = $user->account->companies()->first()->settings;
        $nmo->to_user = $user;

        (new NinjaMailerJob($nmo))->handle();
    }
}
