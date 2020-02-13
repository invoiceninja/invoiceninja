<?php


namespace App\Services\Invoice;


use App\Invoice;
use App\Jobs\Invoice\EmailInvoice;
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
        //client
        //$client = $this->client;

        if (!$reminder_template) {
            $reminder_template = $this->calculateTemplate();
        }

        //Need to determine which email template we are producing
        $message_array = $this->generateTemplateData($reminder_template, $contact);
        EmailInvoice::dispatchNow($message_array);
    }

    private function generateTemplateData(string $reminder_template, $contact): array
    {
        $data = [];

        $client = $this->invoice->customer;

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.quote_message',
                ['amount' => $this->present()->amount(), 'account' => $this->account->present()->name()], null,
                $this->customer->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.quote_subject',
                    ['number' => $this->invoice->present()->invoice_number(), 'account' => $this->invoice->account->present()->name()],
                    null, $this->customer->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    ['number' => $this->invoice->present()->invoice_number(), 'account' => $this->invoice->account->present()->name()],
                    null, $this->invoice->customer->locale());
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
        $customer = $this->invoice->customer;

        //if the invoice
        if ($this->invoice->status_id == Invoice::STATUS_DRAFT || Carbon::parse($this->invoice->due_date) > now()) {
            return 'invoice';
        } elseif ($customer->getSetting('enable_reminder1') !== false && $this->inReminderWindow($customer->getSetting('schedule_reminder1'),
                $customer->getSetting('num_days_reminder1'))) {
            return 'template1';
        } elseif ($customer->getSetting('enable_reminder2') !== false && $this->inReminderWindow($customer->getSetting('schedule_reminder2'),
                $customer->getSetting('num_days_reminder2'))) {
            return 'template2';
        } elseif ($customer->getSetting('enable_reminder3') !== false && $this->inReminderWindow($customer->getSetting('schedule_reminder3'),
                $customer->getSetting('num_days_reminder3'))) {
            return 'template3';
        } else {
            return 'invoice';
        }

        //also implement endless reminders here
    }
}
