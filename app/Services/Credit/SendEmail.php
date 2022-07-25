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

namespace App\Services\Credit;

use App\Helpers\Email\CreditEmail;
use App\Jobs\Credit\EmailCredit;
use App\Jobs\Entity\EmailEntity;
use App\Models\ClientContact;

class SendEmail
{
    public $credit;

    protected $reminder_template;

    protected $contact;

    public function __construct($credit, $reminder_template = null, ClientContact $contact = null)
    {
        $this->credit = $credit;

        $this->reminder_template = $reminder_template;

        $this->contact = $contact;
    }

    /**
     * Builds the correct template to send.
     * @return void
     */
    public function run()
    {
        if (! $this->reminder_template) {
            $this->reminder_template = $this->credit->calculateTemplate('credit');
        }

        $this->credit->invitations->each(function ($invitation) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {

                // $email_builder = (new CreditEmail())->build($invitation, $this->reminder_template);
                // EmailCredit::dispatchNow($email_builder, $invitation, $invitation->company);
                EmailEntity::dispatchSync($invitation, $invitation->company, $this->reminder_template);
            }
        });

        $this->credit->service()->markSent();
    }
}
