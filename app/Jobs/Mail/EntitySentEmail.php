<?php

namespace App\Jobs\Mail;

use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EntitySentEmail implements ShouldQueue
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

    }

    private function setMailDriver(string $driver)
    {
        switch ($driver) {
            case 'default':
                break;
            case 'gmail':
                $this->setGmailMailer();
                break;
            default:
                
                break;
        }
        if($driver == 'default')
            return;
    }

    private function setGmailMailer()
    {
        $sending_user = $this->entity->client->getSetting('gmail_sending_user_id');

    }
}
