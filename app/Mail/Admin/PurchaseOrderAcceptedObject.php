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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use stdClass;

class PurchaseOrderAcceptedObject
{
    public function __construct(public PurchaseOrder $purchase_order, public Company $company, protected bool $use_react_url)
    {
    }

    public function build()
    {
        MultiDB::setDb($this->company->db);

        if (! $this->purchase_order) {
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
        return Number::formatMoney($this->purchase_order->amount, $this->company);
    }

    private function getSubject()
    {
        return
            ctrans(
                'texts.notification_purchase_order_accepted_subject',
                [
                    'vendor' => $this->purchase_order->vendor->present()->name(),
                    'purchase_order' => $this->purchase_order->number,
                ]
            );
    }

    private function getData()
    {
        $settings = $this->company->settings;

        $content = ctrans(
            'texts.notification_purchase_order_accepted',
            [
                    'amount' => $this->getAmount(),
                    'vendor' => $this->purchase_order->vendor->present()->name(),
                    'purchase_order' => $this->purchase_order->number,
                ]
        );

        $data = [
            'title' => $this->getSubject(),
            'content' => $content,
            'url' => $this->purchase_order->invitations->first()->getAdminLink($this->use_react_url),
            'button' => ctrans('texts.view_purchase_order'),
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
