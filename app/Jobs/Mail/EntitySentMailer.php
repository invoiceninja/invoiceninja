<?php

namespace App\Jobs\Mail;

use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\EntitySentObject;
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

class EntitySentMailer extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $company;

    public $user;

    public $invitation;

    public $entity_type;

    public $entity;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($invitation, $entity_type, $user, $company)
    {
        $this->company = $company;

        $this->user = $user;

        $this->invitation = $invitation;

        $this->entity = $invitation->{$entity_type};

        $this->entity_type = $entity_type;
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

        //if we need to set an email driver do it now
        $this->setMailDriver($this->entity->client->getSetting('email_sending_method'));

        $mail_obj = (new EntitySentObject($this->invitation, $this->entity_type))->build();
        $mail_obj->from = [$this->entity->user->email, $this->entity->user->present()->name()];
        
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
            $this->entity->client
        );
    }


}
