<?php

namespace App\Console\Commands;

use App\Mail\TemplateEmail;
use App\Models\User;
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
        $this->sendTemplateEmails('plain');
        $this->sendTemplateEmails('light');
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

        if(!$user){
            $user = factory(\App\Models\User::class)->create([
                'confirmation_code' => '123',
            ]);
        }

         $cc_emails = [config('ninja.testvars.test_email')];
         $bcc_emails = [config('ninja.testvars.test_email')];

        Mail::to(config('ninja.testvars.test_email'))
            ->cc($cc_emails)
            ->bcc($bcc_emails)
            //->replyTo(also_available_if_needed)
            ->send(new TemplateEmail($message, $template, $user));
    }
}
