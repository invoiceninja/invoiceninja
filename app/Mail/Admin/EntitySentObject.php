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

namespace App\Mail\Admin;

use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use stdClass;

class EntitySentObject
{
    public $invitation;

    public $entity_type;

    public $entity;

    public $contact;

    public $company;

    public $settings;

    public $template;

    private $template_subject;

    private $template_body;

    public function __construct($invitation, $entity_type, $template)
    {
        $this->invitation = $invitation;
        $this->entity_type = $entity_type;
        $this->entity = $invitation->{$entity_type};
        $this->contact = $invitation->contact;
        $this->company = $invitation->company;
        $this->template = $template;
    }

    public function build()
    {
        App::forgetInstance('translator');
        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->setTemplate();

        $mail_obj = new stdClass;
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }

    private function setTemplate()
    {
        // nlog($this->template);

        switch ($this->template) {
            case 'invoice':
                $this->template_subject = 'texts.notification_invoice_sent_subject';
                $this->template_body = 'texts.notification_invoice_sent';
                break;
            case 'reminder1':
                $this->template_subject = 'texts.notification_invoice_reminder1_sent_subject';
                $this->template_body = 'texts.notification_invoice_sent';
                break;
            case 'reminder2':
                $this->template_subject = 'texts.notification_invoice_reminder2_sent_subject';
                $this->template_body = 'texts.notification_invoice_sent';
                break;
            case 'reminder3':
                $this->template_subject = 'texts.notification_invoice_reminder3_sent_subject';
                $this->template_body = 'texts.notification_invoice_sent';
                break;
            case 'reminder_endless':
                $this->template_subject = 'texts.notification_invoice_reminder_endless_sent_subject';
                $this->template_body = 'texts.notification_invoice_sent';
                break;
            case 'quote':
                $this->template_subject = 'texts.notification_quote_sent_subject';
                $this->template_body = 'texts.notification_quote_sent';
                break;
            case 'credit':
                $this->template_subject = 'texts.notification_credit_sent_subject';
                $this->template_body = 'texts.notification_credit_sent';
                break;

            default:
                $this->template_subject = 'texts.notification_invoice_sent_subject';
                $this->template_body = 'texts.notification_invoice_sent';
                break;
        }
    }

    private function getAmount()
    {
        return Number::formatMoney($this->entity->amount, $this->entity->client);
    }

    private function getSubject()
    {
        return
            ctrans(
                $this->template_subject,
                [
                    'client' => $this->contact->client->present()->name(),
                    'invoice' => $this->entity->number,
                ]
            );
    }

    private function getMessage()
    {
        return ctrans(
                $this->template_body,
                [
                    'amount' => $this->getAmount(),
                    'client' => $this->contact->client->present()->name(),
                    'invoice' => $this->entity->number,
                ]
            );
    }

    private function getData()
    {
        $settings = $this->entity->client->getMergedSettings();

        return [
            'title' => $this->getSubject(),
            'message' => $this->getMessage(),
            'url' => $this->invitation->getAdminLink(),
            'button' => ctrans("texts.view_{$this->entity_type}"),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
        ];
    }
}
