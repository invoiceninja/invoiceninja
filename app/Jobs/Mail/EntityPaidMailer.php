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

namespace App\Jobs\Mail;

use App\Libraries\MultiDB;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\EntityPaidObject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/

class EntityPaidMailer extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $company;

    public $user;

    public $payment;

    public $entity_type;

    public $entity;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param $payment
     * @param $user
     * @param $company
     */
    public function __construct($payment, $company, $user)
    {
        $this->company = $company;

        $this->user = $user;

        $this->payment = $payment;

        $this->settings = $payment->client->getMergedSettings();
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

        try {
            $mail_obj = (new EntityPaidObject($this->payment))->build();
            $mail_obj->from = [$this->user->email, $this->user->present()->name()];

            //send email
            Mail::to($this->user->email)
                ->send(new EntityNotificationMailer($mail_obj));
        } catch (\Exception $e) {
            $this->failed($e);
            $this->logMailError($e->getMessage(), $this->payment->client);
        }
    }
}
