<?php

namespace App\Services\Invoice;

use App\Helpers\Email\BuildEmail;
use App\Jobs\Invoice\EmailInvoice;
use App\Models\Invoice;
use App\Traits\FormatEmail;
use Illuminate\Support\Carbon;

class SendEmail
{
    use FormatEmail;

    public $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Builds the correct template to send
     * @param string $reminder_template The template name ie reminder1
     * @return array
     */
    public function sendEmail($reminder_template = null, $contact = null): array
    {
        if (!$reminder_template) {
            $reminder_template = $this->invoice->status_id == Invoice::STATUS_DRAFT || Carbon::parse($this->invoice->due_date) > now() ? 'invoice' : $this->invoice->calculateTemplate();
        }

        $email_builder = (new BuildEmail)->buildInvoiceEmail($this->invoice, $reminder_template, $contact);
        EmailInvoice::dispatchNow($email_builder);
    }
}
