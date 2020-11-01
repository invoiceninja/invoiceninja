<?php
/**
 * Created by PhpStorm.
 * User: michael.hampton
 * Date: 14/02/2020
 * Time: 19:51.
 */

namespace App\Helpers\Email;

use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Utils\HtmlEngine;

class QuoteEmail extends EmailBuilder
{
    public function build(QuoteInvitation $invitation, $reminder_template)
    {
        $client = $invitation->contact->client;
        $quote = $invitation->quote;
        $contact = $invitation->contact;

        $this->template_style = $client->getSetting('email_style');

        $body_template = $client->getSetting('email_template_'.$reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.quote_message',
                ['amount' => $quote->amount, 'company' => $quote->company->present()->name()],
                null,
                $quote->client->locale()
            );
        }

        $subject_template = $client->getSetting('email_subject_'.$reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans(
                    'texts.quote_subject',
                    ['number' => $quote->number, 'company' => $quote->company->present()->name()],
                    null,
                    $quote->client->locale()
                );
            } else {
                $subject_template = trans(
                    'texts.reminder_subject',
                    ['number' => $quote->number, 'company' => $quote->company->present()->name()],
                    null,
                    $quote->client->locale()
                );
            }
        }

        $this->setTemplate($quote->client->getSetting('email_style'))
            ->setContact($contact)
            ->setFooter("<a href='{$invitation->getLink()}'>Quote Link</a>")
            ->setVariables((new HtmlEngine($invitation))->makeValues())
            ->setSubject($subject_template)
            ->setBody($body_template);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $invitation->pdf_file_path();
        }

        return $this;
    }
}
