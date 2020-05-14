<?php

namespace App\Jobs\Mail;

use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntitySentObject;
use App\Models\User;
use App\Providers\MailServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class EntitySentEmail extends BaseMailerJob implements ShouldQueue
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
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        //if we need to set an email driver do it now
        $this->setMailDriver($this->entity->client->getSetting('email_sending_method'));

        $mail_obj = (new EntitySentObject($this->invitation, $this->entity_type))->build();
        $mail_obj->from = $this->setFromUser();
    }

}
