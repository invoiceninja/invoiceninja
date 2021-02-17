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

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\PaymentFailureObject;
use App\Mail\NinjaMailer;
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

    public $error;

    public $company;

    public $payment_hash;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $message
     * @param $company
     * @param $amount
     */
    public function __construct($client, $error, $company, $payment_hash)
    {
        $this->company = $company;

        $this->error = $error;

        $this->client = $client;

        $this->payment_hash = $payment_hash;

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

        //Set DB
        MultiDB::setDb($this->company->db);

        //iterate through company_users
        $this->company->company_users->each(function ($company_user) {        

            //determine if this user has the right permissions
            $methods = $this->findCompanyUserNotificationType($company_user, ['payment_failure','all_notifications']);

            //if mail is a method type -fire mail!!
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $mail_obj = (new PaymentFailureObject($this->client, $this->error, $this->company, $this->payment_hash))->build();

                $nmo = new NinjaMailerObject;
                $nmo->mailable = new NinjaMailer($mail_obj);
                $nmo->company = $this->company;
                $nmo->to_user = $company_user->user;
                $nmo->settings = $this->settings;

                NinjaMailerJob::dispatch($nmo);

            }
        });
    }
}
