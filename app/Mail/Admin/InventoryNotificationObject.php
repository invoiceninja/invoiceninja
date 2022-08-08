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

use App\Mail\Engine\PaymentEmailEngine;
use App\Models\Company;
use App\Models\Product;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use stdClass;

class InventoryNotificationObject
{
    public Product $product;

    public Company $company;

    public $settings;

    public string $notification_level;

    public function __construct(Product $product, string $notification_level)
    {
        $this->product = $product;
        $this->company = $product->company;
        $this->settings = $this->company->settings;
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
        return $this->product->in_stock_quantity;
    }

    private function getSubject()
    {
        return
            ctrans(
                'texts.inventory_notification_subject',
                ['product' => $this->product->product_key.': '.$this->product->notes]
            );
    }

    private function getData()
    {
        $data = [
            'title' => $this->getSubject(),
            'content' => ctrans(
                'texts.inventory_notification_body',
                ['amount' => $this->getAmount(),
                    'product' => $this->product->product_key.': '.$this->product->notes,
                ]
            ),
            'url' => config('ninja.app_url'),
            'button' => ctrans('texts.login'),
            'signature' => $this->settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
        ];

        return $data;
    }
}
