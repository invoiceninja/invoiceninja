<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use League\CommonMark\CommonMarkConverter;
use Parsedown;

/**
 * Class PaymentEmailBuilder.
 */
trait PaymentEmailBuilder
{
    /**
     * Builds the correct template to send.
     * @param null $reminder_template The template name ie reminder1
     * @param null $contact
     * @return array
     */
    public function getEmailData($reminder_template = null, $contact = null) :array
    {
        //client
        //$client = $this->client;

        //Need to determine which email template we are producing
        return $this->generateTemplateData($reminder_template, $contact);
    }

    private function generateTemplateData(string $reminder_template, $contact) :array
    {
        $data = [];

        $client = $this->client;

        $body_template = $client->getSetting('email_template_'.$reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.payment_message', ['amount'=>$this->present()->amount(), 'account'=>$this->company->present()->name()], null, $this->client->locale());
        }

        $subject_template = $client->getSetting('payment_subject');

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans('texts.invoice_subject', ['number'=>$this->present()->invoice_number(), 'account'=>$this->company->present()->name()], null, $this->client->locale());
        }

        $data['body'] = $this->parseTemplate($body_template, false, $contact);
        $data['subject'] = $this->parseTemplate($subject_template, true, $contact);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $data['files'][] = $this->pdf_file_path();
        }

        return $data;
    }

    private function parseTemplate(string $template_data, bool $is_markdown, $contact) :string
    {
        //$invoice_variables = $this->makeValues($contact);

        //process variables
        //$data = str_replace(array_keys($invoice_variables), array_values($invoice_variables), $template_data);

        $data = strtr($template_data, $invoice_variables);

        //process markdown
        if ($is_markdown) {
            //$data = Parsedown::instance()->line($data);

            $converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);

            $data = $converter->convert($data);
        }

        return $data;
    }
}
