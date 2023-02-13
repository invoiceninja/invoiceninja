<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Services\Email\MailBuild;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MailEntity extends BaseMailer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Company $company;

    public function __construct(protected $invitation, private ?string $db, public MailObject $mail_object)
    {

        $this->invitation = $invitation;

        $this->company = $invitation->company;

        $this->db = $db;

        $this->mail_object = $mail_object;

        $this->override = $mail_object->override;

    }

    public function handle(MailBuild $builder): void
    {
        MultiDB::setDb($this->db);

        $this->companyCheck();

        //construct mailable

        //spam checks

        //what do we pass into a generaic builder?
        
        //construct mailer
        $mailer = $this->configureMailer()
                       ->trySending();

     
    }

}
