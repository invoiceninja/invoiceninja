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

namespace App\Services\Invoice;

use App\Jobs\Entity\EmailEntity;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;

class SendEmail extends AbstractService
{
    protected $invoice;

    protected $reminder_template;

    protected $contact;

    public function __construct(Invoice $invoice, $reminder_template = null, ClientContact $contact = null)
    {
        $this->invoice = $invoice;

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
            $this->reminder_template = $this->invoice->calculateTemplate('invoice');
        }

        $this->invoice->invitations->each(function ($invitation) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {
                EmailEntity::dispatchSync($invitation, $invitation->company, $this->reminder_template);
            }
        });
    }
}
