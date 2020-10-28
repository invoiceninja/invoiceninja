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

namespace App\Services\Invoice;

use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Entity\EmailEntity;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;
use Illuminate\Support\Carbon;

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
            $email_builder = (new InvoiceEmail())->build($invitation, $this->reminder_template);

            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatchNow($invitation, $invitation->company);

            }
        });
    }
}
