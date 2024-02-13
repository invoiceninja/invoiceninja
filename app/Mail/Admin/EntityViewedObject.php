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
use Illuminate\Support\Facades\App;
use stdClass;

class EntityViewedObject
{
    public $invitation;

    public $entity_type;

    public $entity;

    public $contact;

    public $company;

    public $settings;

    protected $use_react_url;

    public function __construct($invitation, $entity_type, $use_react_url)
    {
        $this->invitation = $invitation;
        $this->entity_type = $entity_type;
        $this->entity = $invitation->{$entity_type};
        $this->contact = $invitation->contact;
        $this->company = $invitation->company;
        $this->use_react_url = $use_react_url;
    }

    public function build()
    {
        if (! $this->entity) {
            return;
        }

        App::forgetInstance('translator');
        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $mail_obj = new stdClass();
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.template.text';
        return $mail_obj;
    }

    private function getAmount()
    {
        if ($this->entity->client) {
            $currency_entity = $this->entity->client;
        } else {
            $currency_entity = $this->company;
        }

        return Number::formatMoney($this->entity->amount, $currency_entity);
    }

    private function getSubject()
    {
        return
            ctrans(
                "texts.notification_{$this->entity_type}_viewed_subject",
                [
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->entity->number,
                ]
            );
    }

    private function getData()
    {
        if ($this->entity->client) {
            $settings = $this->entity->client->getMergedSettings();
        } else {
            $settings = $this->company->settings;
        }

        $content = ctrans(
            "texts.notification_{$this->entity_type}_viewed",
            [
                    'amount' => $this->getAmount(),
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->entity->number,
                ]
        );

        $data = [
            'title' => $this->getSubject(),
            'content' => $content,
            'url' => $this->invitation->getAdminLink($this->use_react_url),
            'button' => ctrans("texts.view_{$this->entity_type}"),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
            'text_body' => $content,
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        return $data;
    }
}
