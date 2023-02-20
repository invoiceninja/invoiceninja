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

use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Mail\Mailable;

class EmailService
{
    /**
     * Used to flag whether we force send the email regardless
     *
     * @var bool $override;
     */
    protected bool $override;

    public Mailable $mailable;

    public function __construct(public EmailObject $email_object, public Company $company)
    {
    }
 
    /**
     * Sends the email via a dispatched job
     * @param  boolean $override Whether the email should send regardless
     * @return void
     */
    public function send($override = false) :void
    {
        $this->override = $override;

        $this->setDefaults()
             ->updateMailable()
             ->email();
    }

    public function sendNow($force = false) :void
    {
        $this->setDefaults()
         ->updateMailable()
         ->email($force);
    }

    private function email($force = false): void
    {
        if ($force) {
            (new EmailMailer($this, $this->mailable))->handle();
        } else {
            EmailMailer::dispatch($this, $this->mailable)->delay(2);
        }
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

   /**
     * On the hosted platform we scan all outbound email for
     * spam. This sequence processes the filters we use on all
     * emails.
     *
     * @return bool
     */
    public function preFlightChecksFail(): bool
    {
        /* If we are migrating data we don't want to fire any emails */
        if ($this->company->is_disabled && !$this->override) {
            return true;
        }

        if (Ninja::isSelfHost()) {
            return false;
        }

        /* To handle spam users we drop all emails from flagged accounts */
        if ($this->company->account && $this->company->account->is_flagged) {
            return true;
        }

        /* On the hosted platform we set default contacts a @example.com email address - we shouldn't send emails to these types of addresses */
        if ($this->hasInValidEmails()) {
            return true;
        }

        /* GMail users are uncapped */
        if (in_array($this->email_object->settings->email_sending_method, ['gmail', 'office365', 'client_postmark', 'client_mailgun'])) {
            return false;
        }

        /* On the hosted platform, if the user is over the email quotas, we do not send the email. */
        if ($this->company->account && $this->company->account->emailQuotaExceeded()) {
            return true;
        }

        /* If the account is verified, we allow emails to flow */
        if ($this->company->account && $this->company->account->is_verified_account) {
            //11-01-2022

            /* Continue to analyse verified accounts in case they later start sending poor quality emails*/
            // if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            //     (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

            return false;
        }

        /* On the hosted platform if the user has not verified their account we fail here - but still check what they are trying to send! */
        if ($this->company->account && !$this->company->account->account_sms_verified) {
            if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
                return (new \Modules\Admin\Jobs\Account\EmailFilter($this->email_object, $this->company))->run();
            }

            return true;
        }

        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
            return (new \Modules\Admin\Jobs\Account\EmailFilter($this->email_object, $this->company))->run();
        }

        return false;
    }

    private function hasInValidEmails(): bool
    {
        foreach ($this->email_object->to as $address_object) {
            if (strpos($address_object->address, '@example.com') !== false) {
                return true;
            }

            if (!str_contains($address_object->address, "@")) {
                return true;
            }

            if ($address_object->address == " ") {
                return true;
            }
        }


        return false;
    }
}
