<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\Models\Quote;
use Illuminate\Support\Carbon;
use League\CommonMark\CommonMarkConverter;
use Parsedown;

/**
 * Class QuoteEmailBuilder.
 */
trait QuoteEmailBuilder
{
    /**
     * Builds the correct template to send.
     * @param null $reminder_template The template name ie reminder1
     * @param null $contact
     * @return array
     */
    public function getEmailData($reminder_template = null, $contact = null) :array
    {
        if (! $reminder_template) {
            $reminder_template = 'quote';

            //$reminder_template = $this->calculateTemplate('quote');
        }

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
            $body_template = trans('texts.quote_message', ['amount'=>$this->present()->amount(), 'account'=>$this->company->present()->name()], null, $this->client->locale());
        }

        $subject_template = $client->getSetting('email_subject_'.$reminder_template);

        if (iconv_strlen($subject_template) == 0) {
            if ($reminder_template == 'quote') {
                $subject_template = trans('texts.quote_subject', ['number'=>$this->present()->invoice_number(), 'account'=>$this->company->present()->name()], null, $this->client->locale());
            } else {
                $subject_template = trans('texts.reminder_subject', ['number'=>$this->present()->invoice_number(), 'account'=>$this->company->present()->name()], null, $this->client->locale());
            }
        }

        $data['body'] = $this->parseTemplate($body_template, true, $contact);
        $data['subject'] = $this->parseTemplate($subject_template, false, $contact);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $data['files'][] = $this->pdf_file_path();
        }

        return $data;
    }

    private function parseTemplate(string $template_data, bool $is_markdown, $contact) :string
    {
        // $quote_variables = $this->makeValues($contact);

        //process variables
//        $data = str_replace(array_keys($quote_variables), array_values($quote_variables), $template_data);
        $data = strtr($template_data, $quote_variables);

        //process markdown
        if ($is_markdown) {
            //$data = Parsedown::instance()->line($data);

            $converter = new CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => true,
            ]);

            $data = $converter->convert($data);
        }

        return $data;
    }

    private function calculateTemplate() :string
    {
        //if invoice is currently a draft, or being marked as sent, this will be the initial email
        $client = $this->client;

        //if the invoice
        if ($this->status_id == Quote::STATUS_DRAFT || Carbon::parse($this->due_date) > now()) {
            return 'quote';
        } elseif ($client->getSetting('enable_reminder1') !== false && $this->inReminderWindow($client->getSetting('schedule_reminder1'), $client->getSetting('num_days_reminder1'))) {
            return 'template1';
        } elseif ($client->getSetting('enable_reminder2') !== false && $this->inReminderWindow($client->getSetting('schedule_reminder2'), $client->getSetting('num_days_reminder2'))) {
            return 'template2';
        } elseif ($client->getSetting('enable_reminder3') !== false && $this->inReminderWindow($client->getSetting('schedule_reminder3'), $client->getSetting('num_days_reminder3'))) {
            return 'template3';
        } else {
            return 'quote';
        }

        //also implement endless reminders here
    }

    private function inReminderWindow($schedule_reminder, $num_days_reminder)
    {
        switch ($schedule_reminder) {
            case 'after_invoice_date':
                return Carbon::parse($this->date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'before_due_date':
                return Carbon::parse($this->due_date)->subDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'after_due_date':
                return Carbon::parse($this->due_date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            default:
                // code...
                break;
        }
    }
}
