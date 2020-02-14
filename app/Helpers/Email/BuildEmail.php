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
    private $variables = [];
    private $contact = null;

    public function buildPaymentEmail(Payment $payment, $contact = null)
    {
        $client = $payment->customer;

        $body_template = $client->getSetting('payment_message');

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {

            $body_template = trans('texts.payment_message',
                ['amount' => $payment->amount, 'account' => $payment->company->present()->name()], null,
                $this->client->locale());
        }

        $subject_template = $client->getSetting('payment_subject');

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans('texts.payment_subject',
                ['number' => $payment->number, 'account' => $payment->company->present()->name()], null,
                $payment->client->locale());
        }

        $this->setTemplate($payment->client->getSetting('email_style'))
            ->setContact($contact)
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("Sent to " . $contact->present()->name());

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $this->pdf_file_path();
        }

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
                ['amount' => $quote->amount, 'account' => $quote->company->present()->name()], null,
                $quote->customer->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.quote_subject',
                    ['number' => $quote->number, 'account' => $quote->company->present()->name()],
                    null, $quote->client->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    ['number' => $quote->number, 'account' => $quote->company->present()->name()],
                    null, $quote->client->locale());
            }
        }

        $this->setTemplate($quote->customer->getSetting('email_style'))
            ->setContact($contact)
            ->setVariables($quote->makeValues($contact))
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$quote->invitations->first()->getLink()}'>Invoice Link</a>");

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $this->pdf_file_path();
        }

        return $this;
    }

    public function buildInvoiceEmail(Invoice $invoice, $reminder_template, $contact = null)
    {
        $client = $invoice->customer;

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.invoice_message',
                ['amount' => $invoice->present()->amount(), 'account' => $invoice->company->present()->name()], null,
                $invoice->client->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.invoice_subject',
                    [
                        'number' => $this->invoice->present()->invoice_number(),
                        'account' => $invoice->company->present()->name()
                    ],
                    null, $invoice->client->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    [
                        'number' => $invoice->present()->invoice_number(),
                        'account' => $invoice->company->present()->name()
                    ],
                    null, $invoice->client->locale());
            }
        }

        $this->setContact($contact)
            ->setTemplate($invoice->client->getSetting('email_style'))
            ->setVariables($invoice->makeValues($contact))
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$invoice->invitations->first()->getLink()}'>Invoice Link</a>");

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $this->pdf_file_path();
        }
        return $this;
    }

    public function buildCreditEmail(Credit $credit)
    {

    }

    private function parseTemplate(string $template_data, bool $is_markdown = true, $contact = null): string
    {
        //process variables
        if (!empty($this->variables)) {
            $data = str_replace(array_keys($this->variables), array_values($this->variables), $template_data);
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
     * @param $footer
     * @return $this
     */
    public function setFooter($footer)
    {
        $this->footer = $footer;
        return $this;
    }

    public function setVariables($variables)
    {
        $this->variables = $variables;
        return $this;
    }

    /**
     * @param $contact
     * @return $this
     */
    public function setContact($contact)
    {
        $this->contact = $contact;
        return $this;
    }

    /**
     * @param $subject
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $this->parseTemplate($subject, false, $this->contact);
        return $this;
    }

    /**
     * @param $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->parseTemplate($body, true);
        return $this;
    }

    /**
     * @param $template_style
     * @return $this
     */
    public function setTemplate($template_style)
    {
        $this->template_style = $template_style;
        return $this;
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
