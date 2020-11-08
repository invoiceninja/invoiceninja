<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Console\Commands;

use App\DataMapper\CompanySettings;
use App\DataMapper\DefaultSettings;
use App\Factory\ClientFactory;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Jobs\Invoice\CreateEntityPdf;
use App\Mail\TemplateEmail;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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
        $this->sendTemplateEmails('plain');
        $this->sendTemplateEmails('light');
        $this->sendTemplateEmails('dark');
    }

    private function sendTemplateEmails($template)
    {
        $faker = Factory::create();

        $message = [
            'title' => 'Invoice XJ-3838',
            'body' => '<div>"Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?"</div>',
            'subject' => 'The Test Subject',
            'footer' => 'Lovely Footer Texts',
        ];

        $user = User::whereEmail('user@example.com')->first();

        if (! $user) {

            $account = Account::factory()->create();

            $user = User::factory()->create([
                'account_id' => $account->id,
                'confirmation_code' => '123',
                'email' => $faker->safeEmail,
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
        } else {
            $company = $user->company_users->first()->company;
            $account = $company->account;
        }

        $client = Client::all()->first();

        if (! $client) {
            $client = ClientFactory::create($company->id, $user->id);
            $client->save();

            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
                'send_email' => true,
                'email' => $faker->safeEmail,
            ]);

            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'send_email' => true,
                'email' => $faker->safeEmail,
            ]);
        }

        $invoice = InvoiceFactory::create($company->id, $user->id);
        $invoice->client_id = $client->id;
        $invoice->setRelation('client', $client);
        $invoice->save();

        $ii = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
        $ii->invoice_id = $invoice->id;
        $ii->client_contact_id = $client->primary_contact()->first()->id;
        $ii->save();

        $invoice->setRelation('invitations', $ii);
        $invoice->service()->markSent()->save();

        CreateEntityPdf::dispatch($invoice->invitations()->first());

        $cc_emails = [config('ninja.testvars.test_email')];
        $bcc_emails = [config('ninja.testvars.test_email')];


        $email_builder->setFooter($message['footer'])
                      ->setSubject($message['subject'])
                      ->setBody($message['body']);

        Mail::to(config('ninja.testvars.test_email'), 'Mr Test')
            ->cc($cc_emails)
            ->bcc($bcc_emails)
            //->replyTo(also_available_if_needed)
            //->send(new TemplateEmail($email_builder, $user, $client));
    }
}
