<?php

namespace App\Jobs\Mail;

use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\EntityPaidObject;
use App\Mail\Admin\EntitySentObject;
use App\Mail\Admin\PaymentFailureObject;
use App\Models\SystemLog;
use App\Models\User;
use App\Providers\MailServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class PaymentFailureMailer extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $client;

    public $message;

    public $company;

    public $amount;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($client, $message, $company, $amount)
    {
        $this->company = $company;

        $this->message = $message;

        $this->client = $client;

        $this->amount = $amount;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        info("entity payment failure mailer");
        //Set DB
        MultiDB::setDb($this->company->db);

        //if we need to set an email driver do it now
        $this->setMailDriver($this->client->getSetting('email_sending_method'));

        $mail_obj = (new PaymentFailureObject($this->client, $this->message, $this->amount, $this->company))->build();
        $mail_obj->from = [$this->company->owner()->email, $this->company->owner()->present()->name()];
        
        //send email
        Mail::to($this->user->email)
            ->send(new EntityNotificationMailer($mail_obj));

        //catch errors
        if (count(Mail::failures()) > 0) {
            $this->logMailError(Mail::failures());
        }

    }

    private function logMailError($errors)
    {
        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $this->client
        );
    }


}
