<?php
namespace App\Jobs\Quote;

use App\Events\Quote\QuoteWasEmailed;
use App\Events\Quote\QuoteWasEmailedAndFailed;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Models\Company;
use App\Models\Quote;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailQuote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $quote;

    public $message_array = [];

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Quote $quote, Company $company)
    {
        $this->quote = $quote;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        /*Jobs are not multi-db aware, need to set! */
        MultiDB::setDB($this->company->db);

        //todo - change runtime config of mail driver if necessary

        $template_style = $this->quote->client->getSetting('email_style');

        $this->quote->invitations->each(function ($invitation) use ($template_style) {
            if ($invitation->contact->email) {
                $message_array = $this->quote->getEmailData('', $invitation->contact);
                $message_array['title'] = &$message_array['subject'];
                $message_array['footer'] = "<a href='{$invitation->getLink()}'>Quote Link</a>";

                //change the runtime config of the mail provider here:

                //send message
                Mail::to($invitation->contact->email, $invitation->contact->present()->name())
                    ->send(new TemplateEmail($message_array, $template_style, $invitation->contact->user, $invitation->contact->client));

                if (count(Mail::failures()) > 0) {
                    event(new QuoteWasEmailedAndFailed($this->quote, Mail::failures()));

                    return $this->logMailError($errors);
                }

                //fire any events
                event(new QuoteWasEmailed($this->quote));

                //sleep(5);
            }
        });
    }

    private function logMailError($errors)
    {
        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $this->quote->client
        );
    }
}
