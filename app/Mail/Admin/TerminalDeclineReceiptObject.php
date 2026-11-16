<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use App\Models\Client;
use App\Models\Company;
use App\Models\PaymentType;
use App\Models\Timezone;
use App\Utils\Ninja;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use stdClass;

/**
 * The customer's receipt for a declined in-person card payment, to the address the merchant entered.
 *
 * Plain strings only, and a generic "Declined": never Stripe's failure or decline code (Stripe asks that a
 * fraud decline is never shown as one), nor anything about the cardholder beyond the brand and last four digits.
 */
class TerminalDeclineReceiptObject
{
    public function __construct(
        public Company $company,
        public Client $client,
        public string $invoice_number,
        public string $amount,
        public string $brand,
        public string $last4,
        public int $declined_at,
    ) {
    }

    public function build(): stdClass
    {
        /* Built during the request: the locale it answers in is put back */
        $locale = App::getLocale();

        try {
            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($this->client->locale());
            $t->replace(Ninja::transformTranslations($this->company->settings));

            $merchant = $this->company->present()->name();
            $card = trim($this->brandName() . ($this->last4 ? ' •••• ' . $this->last4 : ''));

            $rows = [
                ctrans('texts.terminal_merchant') => $merchant,
                ctrans('texts.invoice_number') => $this->invoice_number,
                ctrans('texts.amount') => $this->amount,
                ctrans('texts.terminal_card') => $card,
                ctrans('texts.date') => $this->date(),
                ctrans('texts.status') => ctrans('texts.terminal_declined'),
            ];

            $intro = ctrans('texts.terminal_decline_receipt_body', ['company' => $merchant]);
            $subject = ctrans('texts.terminal_decline_receipt_subject', ['company' => $merchant]);

            $body = '<p>' . e($intro) . '</p><table style="margin: 0 auto;">'
                . collect($rows)->map(fn ($value, $label) => '<tr><td style="padding: 4px 12px;">' . e($label) . '</td><td style="padding: 4px 12px;"><b>' . e($value) . '</b></td></tr>')->implode('')
                . '</table>';

            $text = $intro . "\n\n" . collect($rows)->map(fn ($value, $label) => "{$label}: {$value}")->implode("\n");

            $mail_obj = new stdClass();
            $mail_obj->amount = $this->amount;
            $mail_obj->subject = $subject;
            $mail_obj->data = [
                'subject' => $subject,
                'body' => $body,
                'logo' => $this->company->present()->logo(),
                'settings' => $this->client->getMergedSettings(),
                'whitelabel' => $this->company->account->isPaid(),
                'company' => $this->company,
                'text_body' => $text,
            ];
            $mail_obj->markdown = 'email.template.client';
            $mail_obj->tag = $this->company->company_key;
            $mail_obj->text_view = 'email.template.text';

            return $mail_obj;
        } finally {
            App::forgetInstance('translator');
            App::setLocale($locale);
            Carbon::setLocale($locale);
            app('translator')->replace(Ninja::transformTranslations($this->company->settings));
        }
    }

    /* The network's name as payments show it, Stripe's word for one we don't know */
    private function brandName(): string
    {
        $type = PaymentType::parseCardType(strtolower($this->brand));

        return $type == PaymentType::CREDIT_CARD_OTHER ? ucfirst($this->brand) : (new PaymentType())->name($type);
    }

    /* In the client's own timezone, date format, clock and language */
    private function date(): string
    {
        $time = $this->client->getSetting('military_time') ? 'H:i' : 'g:i a';

        Carbon::setLocale($this->client->locale());

        return Carbon::createFromTimestamp($this->declined_at)
            ->setTimezone(Timezone::query()->whereKey($this->client->getSetting('timezone_id'))->value('name') ?? 'UTC')
            ->translatedFormat($this->client->date_format() . ' ' . $time);
    }
}
