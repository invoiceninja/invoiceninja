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

use App\Models\Product;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use stdClass;

class InventoryNotificationObject
{
    public function __construct(protected Product $product, public string $notification_level, protected bool $use_react_url)
    {
    }

    public function build()
    {
        App::forgetInstance('translator');
        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->product->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->product->company->settings));

        $mail_obj = new stdClass();
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->product->company->company_key;
        $mail_obj->text_view = 'email.template.text';
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
        $content = ctrans(
            'texts.inventory_notification_body',
            ['amount' => $this->getAmount(),
                    'product' => $this->product->product_key.': '.$this->product->notes,
                ]
        );

        $data = [
            'title' => $this->getSubject(),
            'content' => $content,
            'url' => $this->product->portalUrl($this->use_react_url),
            'button' => ctrans('texts.view'),
            'signature' => $this->product->company->settings->email_signature,
            'logo' => $this->product->company->present()->logo(),
            'settings' => $this->product->company->settings,
            'whitelabel' => $this->product->company->account->isPaid() ? true : false,
            'text_body' => $content,
            'template' => $this->product->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        return $data;
    }
}
