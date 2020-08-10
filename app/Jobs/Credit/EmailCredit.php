<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Credit;

use App\Events\Credit\CreditWasEmailed;
use App\Events\Credit\CreditWasEmailedAndFailed;
use App\Jobs\Mail\BaseMailerJob;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Models\Credit;
use App\Models\SystemLog;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/
class EmailCredit extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $credit;

    public $message_array = [];
    
    public $settings;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Credit $credit)
    {
        $this->credit = $credit;

        $this->settings = $credit->client->getMergedSettings();
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        //todo - change runtime config of mail driver if necessary

        $template_style = $this->credit->client->getSetting('email_style');
        
        $this->setMailDriver();

        $this->credit->invitations->each(function ($invitation) use ($template_style) {

            if ($invitation->contact->send_email && $invitation->contact->email) {

                $message_array = $this->credit->getEmailData('', $invitation->contact);
                $message_array['title'] = &$message_array['subject'];
                $message_array['footer'] = "Sent to ".$invitation->contact->present()->name();
                                
                //send message
                Mail::to($invitation->contact->email, $invitation->contact->present()->name())
                ->send(new TemplateEmail($message_array, $template_style, $invitation->contact->user, $invitation->contact->client));

                if (count(Mail::failures()) > 0) {
                    event(new CreditWasEmailedAndFailed($this->credit, $this->credit->company, Mail::failures(), Ninja::eventVars()));
                    
                    return $this->logMailError(Mail::failures(), $this->credit->client);

                }

                //fire any events
                event(new CreditWasEmailed($this->credit, $this->company, Ninja::eventVars()));
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
            $this->credit->client
        );
    }
}
