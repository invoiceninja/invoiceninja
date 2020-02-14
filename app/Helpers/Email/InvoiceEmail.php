<?php
/**
 * Created by PhpStorm.
 * User: michael.hampton
 * Date: 14/02/2020
 * Time: 19:51
 */

namespace App\Helpers\Email;


use App\Invoice;

class InvoiceEmail extends EmailBuilder
{
    public function build(Invoice $invoice, $reminder_template, $contact = null)
    {
        $client = $invoice->client;

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.invoice_message',
                ['amount' => $invoice->present()->amount(), 'company' => $invoice->company->present()->name()], null,
                $invoice->client->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.invoice_subject',
                    [
                        'number' => $this->invoice->present()->invoice_number(),
                        'company' => $invoice->company->present()->name()
                    ],
                    null, $invoice->client->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    [
                        'number' => $invoice->present()->invoice_number(),
                        'company' => $invoice->company->present()->name()
                    ],
                    null, $invoice->client->locale());
            }
        }

        $this->setTemplate($invoice->client->getSetting('email_style'))
            ->setContact($contact)
            ->setVariables($invoice->makeValues($contact))
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$invoice->invitations->first()->getLink()}'>Invoice Link</a>");

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $this->pdf_file_path();
        }
        return $this;
    }
}
