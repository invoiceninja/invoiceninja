<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use App\Models\Company;
use App\Services\Email\EmailObject;
use Illuminate\Mail\Mailable;

class EmailService
{
    protected string $mailer;

    protected bool $override;

    public Mailable $mailable;

    public function __construct(public EmailObject $email_object, public Company $company){}
 
    public function send($override = false) :void
    {
        $this->override = $override;


nlog($this->email_object->subject);
nlog($this->email_object->body);

        $this->setDefaults()
             ->updateMailable()
             ->email();
    }

    public function sendNow($override = false) :void
    {
        $this->setDefaults()
         ->updateMailable()
         ->email(true);
    }

    private function email($force = false): void
    {
        if($force)
            (new EmailMailer($this, $this->mailable))->handle();
        else
            EmailMailer::dispatch($this, $this->mailable)->delay(2);

    }

    private function setDefaults(): self
    {
        $defaults = new EmailDefaults($this, $this->email_object);
        $defaults->run();

        return $this;
    }

    private function updateMailable()
    {
        $this->mailable = new EmailMailable($this->email_object);

        return $this;
    }

}