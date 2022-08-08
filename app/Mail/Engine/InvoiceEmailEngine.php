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

namespace App\Mail\Engine;

use App\DataMapper\EmailTemplateDefaults;
use App\Jobs\Entity\CreateEntityPdf;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Task;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

class InvoiceEmailEngine extends BaseEmailEngine
{
    use MakesHash;

    public $invitation;

    public $client;

    public $invoice;

    public $contact;

    public $reminder_template;

    public $template_data;

    public function __construct($invitation, $reminder_template, $template_data)
    {
        $this->invitation = $invitation;
        $this->reminder_template = $reminder_template;
        $this->client = $invitation->contact->client;
        $this->invoice = $invitation->invoice;
        $this->contact = $invitation->contact;
        $this->template_data = $template_data;
    }

    public function build()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->client->getMergedSettings()));

        if ($this->reminder_template == 'endless_reminder') {
            $this->reminder_template = 'reminder_endless';
        }

        if (is_array($this->template_data) && array_key_exists('body', $this->template_data) && strlen($this->template_data['body']) > 0) {
            $body_template = $this->template_data['body'];
        } elseif (strlen($this->client->getSetting('email_template_'.$this->reminder_template)) > 0) {
            $body_template = $this->client->getSetting('email_template_'.$this->reminder_template);
        } else {
            $body_template = EmailTemplateDefaults::getDefaultTemplate('email_template_'.$this->reminder_template, $this->client->locale());
        }

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.invoice_message',
                [
                    'invoice' => $this->invoice->number,
                    'company' => $this->invoice->company->present()->name(),
                    'amount' => Number::formatMoney($this->invoice->balance, $this->client),
                ],
                null,
                $this->client->locale()
            );

            $body_template .= '<div class="center">$view_button</div>';
        }

        $text_body = trans(
                'texts.invoice_message',
                [
                    'invoice' => $this->invoice->number,
                    'company' => $this->invoice->company->present()->name(),
                    'amount' => Number::formatMoney($this->invoice->balance, $this->client),
                ],
                null,
                $this->client->locale()
            )."\n\n".$this->invitation->getLink();

        if (is_array($this->template_data) && array_key_exists('subject', $this->template_data) && strlen($this->template_data['subject']) > 0) {
            $subject_template = $this->template_data['subject'];
        } elseif (strlen($this->client->getSetting('email_subject_'.$this->reminder_template)) > 0) {
            $subject_template = $this->client->getSetting('email_subject_'.$this->reminder_template);
        } else {
            $subject_template = EmailTemplateDefaults::getDefaultTemplate('email_subject_'.$this->reminder_template, $this->client->locale());
            // $subject_template = $this->client->getSetting('email_subject_'.$this->reminder_template);
        }

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans(
                'texts.invoice_subject',
                [
                    'number' => $this->invoice->number,
                    'account' => $this->invoice->company->present()->name(),
                ],
                null,
                $this->client->locale()
            );
        }

        $this->setTemplate($this->client->getSetting('email_style'))
            ->setContact($this->contact)
            ->setVariables((new HtmlEngine($this->invitation))->makeValues())//move make values into the htmlengine
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$this->invitation->getLink()}'>".ctrans('texts.view_invoice').'</a>')
            ->setViewLink($this->invitation->getLink())
            ->setViewText(ctrans('texts.view_invoice'))
            ->setInvitation($this->invitation)
            ->setTextBody($text_body);

        if ($this->client->getSetting('pdf_email_attachment') !== false && $this->invoice->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
            if (Ninja::isHosted()) {
                $this->setAttachments([$this->invoice->pdf_file_path($this->invitation, 'url', true)]);
            } else {
                $this->setAttachments([$this->invoice->pdf_file_path($this->invitation)]);
            }
        }

        //attach third party documents
        if ($this->client->getSetting('document_email_attachment') !== false && $this->invoice->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {

            // Storage::url
            foreach ($this->invoice->documents as $document) {
                $this->setAttachments([['path' => $document->filePath(), 'name' => $document->name, 'mime' => NULL]]);
            }

            foreach ($this->invoice->company->documents as $document) {
                $this->setAttachments([['path' => $document->filePath(), 'name' => $document->name, 'mime' => NULL]]);
            }

            $line_items = $this->invoice->line_items;

            foreach ($line_items as $item) {
                $expense_ids = [];

                if (property_exists($item, 'expense_id')) {
                    $expense_ids[] = $item->expense_id;
                }

                if (count($expense_ids) > 0) {
                    $expenses = Expense::whereIn('id', $this->transformKeys($expense_ids))
                                       ->where('invoice_documents', 1)
                                       ->cursor()
                                       ->each(function ($expense) {
                                           foreach ($expense->documents as $document) {
                                               $this->setAttachments([['path' => $document->filePath(), 'name' => $document->name, 'mime' => NULL]]);
                                           }
                                       });
                }

                $task_ids = [];

                if (property_exists($item, 'task_id')) {
                    $task_ids[] = $item->task_id;
                }

                if (count($task_ids) > 0 && $this->invoice->company->invoice_task_documents) {
                    $tasks = Task::whereIn('id', $this->transformKeys($task_ids))
                                       ->cursor()
                                       ->each(function ($task) {
                                           foreach ($task->documents as $document) {
                                               $this->setAttachments([['path' => $document->filePath(), 'name' => $document->name, 'mime' => NULL]]);
                                           }
                                       });
                }
            }
        }

        return $this;
    }
}
