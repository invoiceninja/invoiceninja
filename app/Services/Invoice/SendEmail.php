<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Invoice\EmailInvoice;
use App\Models\Invoice;
use App\Services\AbstractService;
use Illuminate\Support\Carbon;

class SendEmail extends AbstractService
{

    protected $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }


    /**
     * Builds the correct template to send
     * @param string $reminder_template The template name ie reminder1
     * @return array
     */
    public function run($reminder_template = null, $contact = null): array
    {
        if (!$reminder_template) {
            $reminder_template = $this->invoice->status_id == Invoice::STATUS_DRAFT || Carbon::parse($this->invoice->due_date) > now() ? 'invoice' : $this->invoice->calculateTemplate();
        }


        $this->invoice->invitations->each(function ($invitation){

            $email_builder = (new InvoiceEmail())->build($invitation, $reminder_template);

            if ($invitation->contact->send && $invitation->contact->email) {
                EmailInvoice::dispatch($email_builder, $invitation, $invitation->company);
            }
        });
    }
}
