<?php
/**
 * Created by PhpStorm.
 * User: michael.hampton
 * Date: 14/02/2020
 * Time: 19:51.
 */

namespace App\Helpers\Email;

use App\Helpers\Email\EntityEmailInterface;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Number;

class InvoiceEmail extends EmailBuilder implements EntityEmailInterface
{
    public function build(InvoiceInvitation $invitation, $reminder_template = null)
    {
        $client = $invitation->contact->client;
        $invoice = $invitation->invoice;
        $contact = $invitation->contact;

        if (! $reminder_template) {
            $reminder_template = $invoice->calculateTemplate();
        }

        $body_template = $client->getSetting('email_template_'.$reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.invoice_message',
                [
                    'invoice' => $invoice->number,
                    'company' => $invoice->company->present()->name(),
                    'amount' => Number::formatMoney($invoice->balance, $invoice->client),
                ],
                null,
                $invoice->client->locale()
            );
        }

        $subject_template = $client->getSetting('email_subject_'.$reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans(
                    'texts.quote_subject',
                    [
                        'number' => $invoice->number,
                        'account' => $invoice->company->present()->name(),
                    ],
                    null,
                    $invoice->client->locale()
                );
            } else {
                $subject_template = trans(
                    'texts.invoice_subject',
                    [
                        'number' => $invoice->number,
                        'account' => $invoice->company->present()->name(),
                    ],
                    null,
                    $invoice->client->locale()
                );
            }
        }

        $this->setTemplate($client->getSetting('email_style'))
            ->setContact($contact)
            ->setVariables($invoice->makeValues($contact))
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$invitation->getLink()}'>".ctrans('texts.view_invoice').'</a>')
            ->setViewLink($invitation->getLink())
            ->setViewText(ctrans('texts.view_invoice'));

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->setAttachments($invitation->pdf_file_path());
        }

        return $this;
    }
}
