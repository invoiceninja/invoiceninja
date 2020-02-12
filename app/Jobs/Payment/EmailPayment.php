<?php
namespace App\Jobs\Payment;

use App\Events\Payment\PaymentWasEmailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Models\Company;
use App\Models\Payment;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payment;

    public $message_array = [];

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Payment $payment, Company $company)
    {
        $this->payment = $payment;

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

        $template_style = $this->payment->client->getSetting('email_style');

        $this->payment->client->contacts->each(function ($contact) use ($template_style) {
            if ($contact->email) {
                $message_array = $this->payment->getEmailData('', $contact);
                $message_array['title'] = &$message_array['subject'];
                $message_array['footer'] = "Sent to ".$contact->present()->name();

                //change the runtime config of the mail provider here:

                //send message
                Mail::to($contact->email, $contact->present()->name())
                    ->send(new TemplateEmail($message_array, $template_style, $contact->user, $contact->client));

                if (count(Mail::failures()) > 0) {
                    event(new PaymentWasEmailedAndFailed($this->payment, Mail::failures()));

                    return $this->logMailError($errors);
                }

                //fire any events
                event(new PaymentWasEmailed($this->payment));

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
            $this->payment->client
        );
    }
}
