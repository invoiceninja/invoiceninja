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

namespace App\Services\Quote;

use App\Jobs\Entity\EmailEntity;
use App\Jobs\Quote\EmailQuote;
use App\Models\ClientContact;
use App\Models\Quote;

class SendEmail
{
    public $quote;

    protected $reminder_template;

    protected $contact;

    public function __construct($quote, $reminder_template = null, ClientContact $contact = null)
    {
        $this->quote = $quote;

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
            $this->reminder_template = $this->quote->calculateTemplate('quote');
        }

        $this->quote->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatchNow($invitation, $invitation->company, $this->reminder_template);

            }
        });

        $this->quote->service()->markSent();
    }
}
