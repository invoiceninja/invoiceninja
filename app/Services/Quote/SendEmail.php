<?php

namespace App\Services\Quote;

use App\Helpers\Email\BuildEmail;
use App\Jobs\Quote\EmailQuote;
use App\Models\Quote;
use App\Traits\FormatEmail;

class SendEmail
{
    use FormatEmail;

    public $quote;

    public function __construct($quote)
    {
        $this->quote = $quote;
    }

    /**
     * Builds the correct template to send
     * @param string $reminder_template The template name ie reminder1
     * @return array
     */
    public function sendEmail($reminder_template = null, $contact = null): array
    {
        if (!$reminder_template) {
            $reminder_template = $this->quote->status_id == Quote::STATUS_DRAFT || Carbon::parse($this->quote->due_date) > now() ? 'invoice' : $this->quote->calculateTemplate();
        }

        $email_builder = (new BuildEmail())->buildQuoteEmail($this->quote, $reminder_template, $contact);

        $this->quote->invitations->each(function ($invitation) use ($email_builder) {
            if ($invitation->contact->send_invoice && $invitation->contact->email) {
                EmailQuote::dispatchNow($this->quote, $this->quote->company, $email_builder, $invitation);
            }
        });


    }
}
