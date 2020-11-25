<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\Admin;

use App\Utils\Number;
use stdClass;

class EntitySentObject
{
    public $invitation;

    public $entity_type;

    public $entity;

    public $contact;

    public $company;

    public $settings;

    public function __construct($invitation, $entity_type)
    {
        $this->invitation = $invitation;
        $this->entity_type = $entity_type;
        $this->entity = $invitation->{$entity_type};
        $this->contact = $invitation->contact;
        $this->company = $invitation->company;
    }

    public function build()
    {
        $mail_obj = new stdClass;
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }

    private function getAmount()
    {
        return Number::formatMoney($this->entity->amount, $this->entity->client);
    }

    private function getSubject()
    {
        return
            ctrans(
                "texts.notification_{$this->entity_type}_sent_subject",
                [
                        'client' => $this->contact->present()->name(),
                        'invoice' => $this->entity->number,
                    ]
            );
    }

    private function getData()
    {
        $settings = $this->entity->client->getMergedSettings();

        return [
            'title' => $this->getSubject(),
            'message' => ctrans(
                "texts.notification_{$this->entity_type}_sent",
                [
                    'amount' => $this->getAmount(),
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->entity->number,
                ]
            ),
            'url' => $this->invitation->getAdminLink(),
            'button' => ctrans("texts.view_{$this->entity_type}"),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
        ];
    }
}
