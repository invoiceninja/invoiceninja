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
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/

class PaymentFailureMailer extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UserNotifies;

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

        //iterate through company_users
        $this->company->company_users->each(function ($company_user){

            //determine if this user has the right permissions
            $methods = $this->findCompanyUserNotificationType($company_user, ['payment_failure']);

            //if mail is a method type -fire mail!!
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $mail_obj = (new PaymentFailureObject($this->client, $this->message, $this->amount, $this->company))->build();
                $mail_obj->from = [$this->company->owner()->email, $this->company->owner()->present()->name()];
                
                //send email
                Mail::to($company_user->user->email)
                    ->send(new EntityNotificationMailer($mail_obj));

                //catch errors
                if (count(Mail::failures()) > 0) {
                    $this->logMailError(Mail::failures());
                }

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
            $this->client
        );
    }


}
