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

namespace App\Mail\Admin;

use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use stdClass;

class EntityFailedSendObject
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

    private $message_content;

    protected $use_react_url;

    public function __construct($invitation, $entity_type, $template, $message_content, $use_react_url)
    {
        $this->invitation = $invitation;
        $this->entity_type = $entity_type;
        $this->entity = $invitation->{$entity_type};
        $this->contact = $invitation->contact;
        $this->company = $invitation->company;
        $this->template = $template;
        $this->message_content = $message_content;
        $this->use_react_url = $use_react_url;
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

        $mail_obj = new stdClass();
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.template.text';

        return $mail_obj;
    }

    private function setTemplate()
    {

        switch ($this->template) {
            case 'invoice':
            case 'reminder1':
            case 'reminder2':
            case 'reminder3':
            case 'reminder_endless':
                $this->template_subject = 'texts.notification_invoice_bounced_subject';
                $this->template_body = 'texts.notification_invoice_bounced';
                break;
            case 'quote':
                $this->template_subject = 'texts.notification_quote_bounced_subject';
                $this->template_body = 'texts.notification_quote_bounced';
                break;
            case 'credit':
                $this->template_subject = 'texts.notification_credit_bounced_subject';
                $this->template_body = 'texts.notification_credit_bounced';
                break;
            case 'purchase_order':
                $this->template_subject = 'texts.notification_purchase_order_bounced_subject';
                $this->template_body = 'texts.notification_purchase_order_bounced';
                break;
            default:
                $this->template_subject = 'texts.notification_invoice_bounced_subject';
                $this->template_body = 'texts.notification_invoice_bounced';
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
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->entity->number,
                ]
            );
    }

    private function getData()
    {
        $settings = $this->entity->client->getMergedSettings();
        $signature = $settings->email_signature;

        $html_variables = (new HtmlEngine($this->invitation))->makeValues();
        $signature = str_replace(array_keys($html_variables), array_values($html_variables), $signature);
        $content = ctrans(
            $this->template_body,
            [
                    'amount' => $this->getAmount(),
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->entity->number,
                    'error' => $this->message_content ?? '',
                    'contact' => $this->contact->present()->name(),
                ]
        );

        $data = [
            "title" => $this->getSubject(),
            "content" => $content,
            "url" => $this->invitation->getAdminLink($this->use_react_url),
            "button" => ctrans("texts.view_{$this->entity_type}"),
            "signature" => $signature,
            "logo" => $this->company->present()->logo(),
            "settings" => $settings,
            "whitelabel" => $this->company->account->isPaid() ? true : false,
            "text_body" => str_replace("<br>", "\n", $content),
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        return $data;

    }
}
