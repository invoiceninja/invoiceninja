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

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;
use stdClass;

class PaymentFailureObject
{
    use MakesHash;

    public Client $client;

    public string $error;

    public Company $company;

    public $amount;

    public ?PaymentHash $payment_hash;
    // private $invoices;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $message
     * @param $company
     * @param $amount
     */
    public function __construct(Client $client, string $error, Company $company, $amount, ?PaymentHash $payment_hash)
    {
        $this->client = $client;

        $this->error = $error;

        $this->company = $company;

        $this->amount = $amount;

        $this->company = $company;

        $this->payment_hash = $payment_hash;
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
        return $this->amount;
    }

    private function getSubject()
    {
        return
            ctrans(
                'texts.payment_failed_subject',
                ['client' => $this->client->present()->name()]
            );
    }

    private function getData()
    {
        $signature = $this->client->getSetting('email_signature');

        $data = [
            'title' => ctrans(
                'texts.payment_failed_subject',
                [
                    'client' => $this->client->present()->name(),
                ]
            ),
            'content' => ctrans(
                'texts.notification_invoice_payment_failed',
                [
                    'client' => $this->client->present()->name(),
                    'invoice' => $this->getDescription(),
                    'amount' => Number::formatMoney($this->amount, $this->client),
                ]),
            'signature' => $signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->client->getMergedSettings(),
            'whitelabel' => $this->company->account->isPaid() ? true : false,
            'url' => config('ninja.app_url'),
            'button' => ctrans('texts.login'),
            'additional_info' => $this->error,
        ];

        return $data;
    }

    public function getDescription(bool $abbreviated = false)
    {
        if (! $this->payment_hash) {
            return '';
        }

        return \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray());
    }
}
