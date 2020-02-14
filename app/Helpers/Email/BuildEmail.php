<?php


namespace App\Helpers\Email;


use App\Credit;
use App\Invoice;
use App\Payment;
use App\Quote;
use League\CommonMark\CommonMarkConverter;

class BuildEmail
{
    private $subject;
    private $body;
    private $recipients;
    private $attachments;
    private $footer;
    private $template_style;

    public function buildPaymentEmail(Payment $payment, $contact = null)
    {
        $client = $payment->customer;
        $this->template_style = $payment->customer->getSetting('email_style');

        $body_template = $client->getSetting('payment_message');

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {

            $body_template = trans('texts.payment_message',
                ['amount' => $payment->amount, 'account' => $payment->account->present()->name()], null,
                $this->customer->locale());
        }

        $subject_template = $client->getSetting('payment_subject');

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans('texts.payment_subject',
                ['number' => $payment->number, 'account' => $payment->account->present()->name()], null,
                $payment->customer->locale());
        }

        //$invoice_variables = $this->makeValues($contact);

        $this->body = $this->parseTemplate($body_template, [], true, $contact);
        $this->subject = $this->parseTemplate($subject_template, [], false, $contact);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $this->pdf_file_path();
        }

        $this->footer = "Sent to " . $contact->present()->name();
        $this->recipients[0]['name'] = $contact->present()->name();
        $this->recipients[0]['email'] = $contact->email;

        return $this;
    }

    public function buildQuoteEmail(Quote $quote, $reminder_template, $contact = null)
    {
        $client = $quote->customer;
        $this->template_style = $quote->customer->getSetting('email_style');

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.quote_message',
                ['amount' => $quote->amount, 'account' => $quote->account->present()->name()], null,
                $quote->customer->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.quote_subject',
                    ['number' => $quote->number, 'account' => $quote->account->present()->name()],
                    null, $quote->customer->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    ['number' => $quote->number, 'account' => $quote->account->present()->name()],
                    null, $quote->customer->locale());
            }
        }

        $invoice_variables = $quote->makeValues($contact);
        $this->body = $this->parseTemplate($body_template, $invoice_variables, true, $contact);
        $this->subject = $this->parseTemplate($subject_template, $invoice_variables, false, $contact);
        $this->buildRecipientsForInvitations($quote);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $this->pdf_file_path();
        }

        return $this;
    }

    public function buildInvoiceEmail(Invoice $invoice, $reminder_template, $contact = null)
    {
        $client = $invoice->customer;
        $this->template_style = $invoice->customer->getSetting('email_style');

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.invoice_message',
                ['amount' => $invoice->present()->amount(), 'account' => $invoice->account->present()->name()], null,
                $invoice->customer->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.invoice_subject',
                    ['number' => $this->invoice->present()->invoice_number(), 'account' => $invoice->account->present()->name()],
                    null, $invoice->customer->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    ['number' => $invoice->present()->invoice_number(), 'account' => $invoice->account->present()->name()],
                    null, $invoice->customer->locale());
            }
        }

        $invoice_variables = $invoice->makeValues($contact);
        $this->body = $this->parseTemplate($body_template, $invoice_variables, true, $contact);
        $this->subject = $this->parseTemplate($subject_template, $invoice_variables, false, $contact);
        $this->buildRecipientsForInvitations($invoice);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $this->pdf_file_path();
        }
        return $this;
    }

    private function buildRecipientsForInvitations($entity)
    {
        $entity->invitations->each(function ($invitation) {
            if ($invitation->contact->email) {
                $this->recipients[] = array(
                    'footer' => "<a href='{$invitation->getLink()}'>Invoice Link</a>",
                    'name' => $invitation->contact->present()->name(),
                    'email' => $invitation->contact->email
                );
            }
        });
    }

    public function buildCreditEmail(Credit $credit)
    {

    }

    private function parseTemplate(string $template_data, array $variables = [], bool $is_markdown = true, $contact = null): string
    {

        //process variables
        if(!empty($variables)) {
            $data = str_replace(array_keys($variables), array_values($variables), $template_data);
        }

        //process markdown
        if ($is_markdown) {
            //$data = Parsedown::instance()->line($data);

            $converter = new CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => true,
            ]);

            $data = $converter->convertToHtml($data);
        }

        return $data;
    }

    /**
     * @param mixed $footer
     */
    public function setFooter($footer): void
    {
        $this->footer = $footer;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return mixed
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * @return mixed
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @return mixed
     */
    public function getFooter()
    {
        return $this->footer;
    }

    /**
     * @return mixed
     */
    public function getTemplate()
    {
        return $this->template_style;
    }
}
