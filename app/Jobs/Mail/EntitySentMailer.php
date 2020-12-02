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
use App\Mail\Admin\EntitySentObject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/

class EntitySentMailer extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $company;

    public $user;

    public $invitation;

    public $entity_type;

    public $entity;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param $invitation
     * @param $entity_type
     * @param $user
     * @param $company
     */
    public function __construct($invitation, $entity_type, $user, $company)
    {
        $this->company = $company;

        $this->user = $user;

        $this->invitation = $invitation;

        $this->entity = $invitation->{$entity_type};

        $this->entity_type = $entity_type;

        $this->settings = $invitation->contact->client->getMergedSettings();
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

        $mail_obj = (new EntitySentObject($this->invitation, $this->entity_type))->build();
        $mail_obj->from = [config('mail.from.address'), config('mail.from.name')];

        try {
            Mail::to($this->user->email)
                ->send(new EntityNotificationMailer($mail_obj));
        } catch (\Exception $e) {
            $this->failed($e);
            $this->logMailError($e->getMessage(), $this->entity->client);
        }
    }
}
