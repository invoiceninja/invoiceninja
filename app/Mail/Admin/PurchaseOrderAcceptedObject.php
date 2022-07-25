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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use stdClass;

class PurchaseOrderAcceptedObject
{
    public $purchase_order;

    public $company;

    public $settings;

    public function __construct(PurchaseOrder $purchase_order, Company $company)
    {
        $this->purchase_order = $purchase_order;
        $this->company = $company;
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

        $data = [
            'title' => $this->getSubject(),
            'message' => ctrans(
                'texts.notification_purchase_order_accepted',
                [
                    'amount' => $this->getAmount(),
                    'vendor' => $this->purchase_order->vendor->present()->name(),
                    'purchase_order' => $this->purchase_order->number,
                ]
            ),
            'url' => $this->purchase_order->invitations->first()->getAdminLink(),
            'button' => ctrans('texts.view_purchase_order'),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
        ];

        return $data;
    }
}
