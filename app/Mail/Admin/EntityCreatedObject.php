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

use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use stdClass;

class EntityCreatedObject
{
    public $entity_type;

    public $entity;

    public $client;

    public $company;

    public $settings;

    private $template_subject;

    private $template_body;

    protected bool $use_react_link;

    public function __construct($entity, $entity_type, $use_react_link = false)
    {
        $this->entity_type = $entity_type;
        $this->entity = $entity;
        $this->use_react_link = $use_react_link;
    }

    /**
     * @return stdClass
     * @throws BindingResolutionException
     */
    public function build(): stdClass
    {
        App::forgetInstance('translator');
        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->entity->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->entity->company->settings));
        $this->setTemplate();
        $this->company = $this->entity->company;

        if ($this->entity_type == 'purchase_order') {
            $this->entity->load('vendor.company');

            $mail_obj = new stdClass();
            $mail_obj->amount = Number::formatMoney($this->entity->amount, $this->entity->vendor);

            $mail_obj->subject = ctrans(
                $this->template_subject,
                [
                    'vendor' => $this->entity->vendor->present()->name(),
                    'purchase_order' => $this->entity->number,
                ]
            );

            $mail_obj->markdown = 'email.admin.generic';
            $mail_obj->text_view = 'email.template.text';

            $content = ctrans(
                $this->template_body,
                [
                                        'amount' => $mail_obj->amount,
                                        'vendor' => $this->entity->vendor->present()->name(),
                                        'purchase_order' => $this->entity->number,
                                    ]
            );

            $mail_obj->data = [
                                'title' => $mail_obj->subject,
                                'content' => $content,
                                'url' => $this->entity->invitations()->first()->getAdminLink($this->use_react_link),
                                'button' => ctrans("texts.view_{$this->entity_type}"),
                                'signature' => $this->company->settings->email_signature,
                                'logo' => $this->company->present()->logo(),
                                'settings' => $this->company->settings,
                                'whitelabel' => $this->company->account->isPaid() ? true : false,
                                'text_body' => str_replace(['$view_button','$viewButton','$viewLink','$view_url'], '$view_url', $content),
                                'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
                ];
        } else {
            $this->entity->load('client.country', 'client.company');
            $this->client = $this->entity->client;

            $mail_obj = new stdClass();
            $mail_obj->amount = $this->getAmount();
            $mail_obj->subject = $this->getSubject();
            $mail_obj->data = $this->getData();
            $mail_obj->markdown = 'email.admin.generic';
            $mail_obj->text_view = 'email.template.text';
        }

        return $mail_obj;
    }

    private function setTemplate()
    {
        switch ($this->entity_type) {
            case 'invoice':
                $this->template_subject = 'texts.notification_invoice_created_subject';
                $this->template_body = 'texts.notification_invoice_created_body';
                break;
            case 'quote':
                $this->template_subject = 'texts.notification_quote_created_subject';
                $this->template_body = 'texts.notification_quote_created_body';
                break;
            case 'credit':
                $this->template_subject = 'texts.notification_credit_created_subject';
                $this->template_body = 'texts.notification_credit_created_body';
                break;
            case 'purchase_order':
                $this->template_subject = 'texts.notification_purchase_order_created_subject';
                $this->template_body = 'texts.notification_purchase_order_created_body';
                break;
            default:
                $this->template_subject = 'texts.notification_invoice_created_subject';
                $this->template_body = 'texts.notification_invoice_created_body';
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
                    'client' => $this->client->present()->name(),
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
                'client' => $this->client->present()->name(),
                'invoice' => $this->entity->number,
            ]
        );
    }

    private function getData()
    {
        $settings = $this->entity->client->getMergedSettings();

        $content = $this->getMessage();

        return [
            'title' => $this->getSubject(),
            'content' => $content,
            'url' => $this->entity->invitations()->first()->getAdminLink($this->use_react_link),
            'button' => ctrans("texts.view_{$this->entity_type}"),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
            'text_body' => str_replace(['$view_button','$viewButton','$view_link','$view_button'], '$view_url', $content),
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];
    }
}
