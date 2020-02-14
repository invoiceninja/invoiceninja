<?php
/**
 * Created by PhpStorm.
 * User: michael.hampton
 * Date: 14/02/2020
 * Time: 19:51
 */

namespace App\Helpers\Email;

use App\Models\Quote;


class QuoteEmail extends EmailBuilder
{

    public function build(Quote $quote, $reminder_template, $contact = null)
    {
        $client = $quote->client;
        $this->template_style = $quote->client->getSetting('email_style');

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.quote_message',
                ['amount' => $quote->amount, 'company' => $quote->company->present()->name()], null,
                $quote->client->locale());
        }

        $subject_template = $client->getSetting('email_subject_' . $reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.quote_subject',
                    ['number' => $quote->number, 'company' => $quote->company->present()->name()],
                    null, $quote->client->locale());
            } else {
                $subject_template = trans('texts.reminder_subject',
                    ['number' => $quote->number, 'company' => $quote->company->present()->name()],
                    null, $quote->client->locale());
            }
        }

        $this->setTemplate($quote->client->getSetting('email_style'))
            ->setContact($contact)
            ->setFooter("<a href='{$quote->invitations->first()->getLink()}'>Invoice Link</a>")
            ->setVariables($quote->makeValues($contact))
            ->setSubject($subject_template)
            ->setBody($body_template);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $this->attachments = $quote->pdf_file_path();
        }

        return $this;
    }
}
