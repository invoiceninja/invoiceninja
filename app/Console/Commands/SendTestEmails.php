<?php

namespace App\Console\Commands;

use App\Factory\ClientFactory;
use App\Mail\TemplateEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\User;
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
        sleep(5);
        $this->sendTemplateEmails('light');
        sleep(5);
        $this->sendTemplateEmails('dark');
    }

    private function sendTemplateEmails($template)
    {
        $message = [
            'title' => 'Invoice XJ-3838',
            'body' => '<div>"Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?"</div>',
            'subject' => 'The Test Subject',
            'footer' => 'Lovely Footer Texts',
        ];

        $user = User::whereEmail('user@example.com')->first();
        $client = Client::all()->first();

        if(!$user){
            $user = factory(\App\Models\User::class)->create([
                'confirmation_code' => '123',
                'email' => 'admin@business.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);
        }

        if(!$client) {
             
             $client = ClientFactory::create($user->company()->id, $user->id);
             $client->save();

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
                'send_invoice' => true,
                'email' => 'exy@example.com',
            ]);

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'send_invoice' => true,
                'email' => 'exy2@example.com',
            ]);
        }

         $cc_emails = [config('ninja.testvars.test_email')];
         $bcc_emails = [config('ninja.testvars.test_email')];

        Mail::to(config('ninja.testvars.test_email'),'Mr Test')
            ->cc($cc_emails)
            ->bcc($bcc_emails)
            //->replyTo(also_available_if_needed)
            ->send(new TemplateEmail($message, $template, $user, $client));
    }

}
