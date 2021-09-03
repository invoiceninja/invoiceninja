<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use stdClass;
use App;

class PendingPaymentObject
{
    use MakesHash;

    public $client;

    public $payment_gateway;

    public $company;

    public $amount;

    // private $invoices;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $payment_gateway
     * @param $company
     * @param $amount
     */
    public function __construct($client, $payment_gateway, $company, $amount)
    {
        $this->client = $client;
        $this->payment_gateway = $payment_gateway;
        $this->amount = $amount;
        $this->company = $company;
    }

    public function build(): stdClass
    {
        App::forgetInstance('translator');
        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $mail_obj = new stdClass;
        $mail_obj->amount = $this->amount;
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }

    private function getSubject(): string
    {
        return
            ctrans(
                'texts.pending_payment_subject',
                ['client' => $this->client->present()->name()]
            );
    }

    private function getData(): array
    {
        $signature = $this->client->getSetting('email_signature');
        return [
            'title' => $this->getSubject(),
            'content' => ctrans(
                'texts.pending_payment_body',
                [
                    'client' => $this->client->present()->name(),
                    'payment_gateway' => $this->payment_gateway,
                    'amount' => Number::formatMoney($this->amount, $this->client),
                ]
            ),
            'signature' => $signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->client->getMergedSettings(),
            'whitelabel' => $this->company->account->isPaid(),
            'url' => config('ninja.app_url'),
            'button' => ctrans('texts.login')
        ];
    }
}
