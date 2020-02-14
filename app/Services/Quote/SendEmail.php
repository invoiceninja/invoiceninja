<?php

namespace App\Services\Quote;

use App\Helpers\Email\BuildEmail;
use App\Jobs\Quote\EmailQuote;
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
            $reminder_template = $this->calculateTemplate();
        }

        $email_builder = (new BuildEmail())->buildQuoteEmail($this->quote, $reminder_template, $contact);

        EmailQuote::dispatchNow($email_builder);
    }
}
