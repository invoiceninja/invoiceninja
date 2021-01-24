<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Mail;

use App\Libraries\MultiDB;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\PaymentFailureObject;
use App\Models\User;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/

class PaymentFailureMailer extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UserNotifies;

    public $client;

    public $message;

    public $company;

    public $amount;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $message
     * @param $company
     * @param $amount
     */
    public function __construct($client, $message, $company, $amount)
    {
        $this->company = $company;

        $this->message = $message;

        $this->client = $client;

        $this->amount = $amount;

        $this->company = $company;

        $this->settings = $client->getMergedSettings();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /*If we are migrating data we don't want to fire these notification*/
        if ($this->company->is_disabled) {
            return true;
        }
        
        //Set DB
        MultiDB::setDb($this->company->db);

        //if we need to set an email driver do it now
        $this->setMailDriver();

        //iterate through company_users
        $this->company->company_users->each(function ($company_user) {

            //determine if this user has the right permissions
            $methods = $this->findCompanyUserNotificationType($company_user, ['payment_failure']);

            //if mail is a method type -fire mail!!
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $mail_obj = (new PaymentFailureObject($this->client, $this->message, $this->amount, $this->company))->build();
                $mail_obj->from = [config('mail.from.address'), config('mail.from.name')];

                //send email
                try {
                    Mail::to($company_user->user->email)
                        ->send(new EntityNotificationMailer($mail_obj));
                } catch (\Exception $e) {
                    //$this->failed($e);
                    $this->logMailError($e->getMessage(), $this->client);
                }
            }
        });
    }
}
