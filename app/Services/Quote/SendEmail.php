<?php

namespace App\Services\Quote;

use App\Jobs\Quote\EmailQuote;
use App\Quote;
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

        //Need to determine which email template we are producing
        $message_array = $this->generateTemplateData($reminder_template, $contact);
        EmailQuote::dispatchNow($message_array);
    }

    private function generateTemplateData(string $reminder_template, $contact): array
    {
        $data = [];

        $client = $this->quote->customer;

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.quote_message',
                ['amount' => $this->quote->amount, 'account' => $this->account->present()->name()], null,
                $this->customer->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.quote_subject',
                    ['number' => $this->quote->number, 'account' => $this->quote->account->present()->name()],
                    null, $this->customer->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    ['number' => $this->quote->number, 'account' => $this->quote->account->present()->name()],
                    null, $this->quote->customer->locale());
            }
        }

        $data['body'] = $this->parseTemplate($body_template, true, $contact);
        $data['subject'] = $this->parseTemplate($subject_template, false, $contact);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $data['files'][] = $this->pdf_file_path();
        }

        return $data;
    }

    private function calculateTemplate(): string
    {
        //if invoice is currently a draft, or being marked as sent, this will be the initial email
        $client = $this->quote->customer;

        //if the invoice
        if ($this->quote->status_id == Quote::STATUS_DRAFT || Carbon::parse($this->quote->due_date) > now()) {
            return 'quote';
        } elseif ($client->getSetting('enable_reminder1') !== false && $this->inReminderWindow($client->getSetting('schedule_reminder1'),
                $client->getSetting('num_days_reminder1'))) {
            return 'template1';
        } elseif ($client->getSetting('enable_reminder2') !== false && $this->inReminderWindow($client->getSetting('schedule_reminder2'),
                $client->getSetting('num_days_reminder2'))) {
            return 'template2';
        } elseif ($client->getSetting('enable_reminder3') !== false && $this->inReminderWindow($client->getSetting('schedule_reminder3'),
                $client->getSetting('num_days_reminder3'))) {
            return 'template3';
        } else {
            return 'quote';
        }

        //also implement endless reminders here
    }
}
