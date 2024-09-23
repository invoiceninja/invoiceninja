<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Payment;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;

class PaymentHtmlEngine
{
    use MakesDates;

    public Client $client;

    public mixed $settings;

    public Company $company;

    private Helpers $helpers;

    private ?string $payment_template_body;

    private ?string $payment_template_subject;

    public function __construct(public Payment $payment, public ?ClientContact $contact = null)
    {
        $this->payment = $payment;
        $this->company = $payment->company;
        $this->client = $payment->client;
        $this->contact = $contact ?: $this->client->contacts()->first();
        $this->contact->load('client.company');
        $this->settings = $this->client->getMergedSettings();
        $this->helpers = new Helpers();
    }

    public function setSettings($settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function makePaymentVariables()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->contact->preferredLocale());
        $t->replace(Ninja::transformTranslations($this->client->getMergedSettings()));

        $data = [];

        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$number'] = ['value' => $this->payment->number ?: ' ', 'label' => ctrans('texts.payment_number')];
        $data['$payment.number'] = &$data['$number'];
        $data['$entity'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$payment.amount'] = ['value' => Number::formatMoney($this->payment->amount, $this->client) ?: ' ', 'label' => ctrans('texts.amount')];
        $data['$payment.refunded'] = ['value' => Number::formatMoney($this->payment->refunded, $this->client) ?: ' ', 'label' => ctrans('texts.refund')];
        $data['$amount'] = &$data['$payment.amount'];
        $data['$payment.date'] = ['value' => $this->translateDate($this->payment->date, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.payment_date')];
        $data['$transaction_reference'] = ['value' => $this->payment->transaction_reference, 'label' => ctrans('texts.transaction_reference')];

        $data['$font_size'] = ['value' => $this->settings->font_size . 'px !important;', 'label' => ''];
        $data['$font_name'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['name'], 'label' => ''];
        $data['$font_url'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['url'], 'label' => ''];
        $data['$secondary_font_name'] = ['value' => Helpers::resolveFont($this->settings->secondary_font)['name'], 'label' => ''];
        $data['$secondary_font_url'] = ['value' => Helpers::resolveFont($this->settings->secondary_font)['url'], 'label' => ''];
        $data['$invoiceninja.whitelabel'] = ['value' => 'https://invoicing.co/images/new_logo.png', 'label' => ''];
        $data['$primary_color'] = ['value' => $this->settings->primary_color, 'label' => ''];
        $data['$secondary_color'] = ['value' => $this->settings->secondary_color, 'label' => ''];

        $data['$payment1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment1', $this->payment->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment1')];
        $data['$payment2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment2', $this->payment->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment2')];
        $data['$payment3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment3', $this->payment->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment3')];
        $data['$payment4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment4', $this->payment->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment4')];

        $data['$custom1'] = &$data['$payment1'];
        $data['$custom2'] = &$data['$payment2'];
        $data['$custom3'] = &$data['$payment3'];
        $data['$custom4'] = &$data['$payment4'];

        $data['$client1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client1', $this->client->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client1')];
        $data['$client2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client2', $this->client->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client2')];
        $data['$client3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client3', $this->client->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client3')];
        $data['$client4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client4', $this->client->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client4')];
        $data['$address1'] = ['value' => $this->client->address1 ?: ' ', 'label' => ctrans('texts.address1')];
        $data['$address2'] = ['value' => $this->client->address2 ?: ' ', 'label' => ctrans('texts.address2')];
        $data['$id_number'] = ['value' => $this->client->id_number ?: ' ', 'label' => ctrans('texts.id_number')];
        $data['$client.number'] = ['value' => $this->client->number ?: ' ', 'label' => ctrans('texts.number')];
        $data['$vat_number'] = ['value' => $this->client->vat_number ?: ' ', 'label' => ctrans('texts.vat_number')];
        $data['$website'] = ['value' => $this->client->present()->website() ?: ' ', 'label' => ctrans('texts.website')];
        $data['$phone'] = ['value' => $this->client->present()->phone() ?: ' ', 'label' => ctrans('texts.phone')];
        $data['$country'] = ['value' => isset($this->client->country->name) ? $this->client->country->name : '', 'label' => ctrans('texts.country')];
        $data['$email'] = ['value' => isset($this->contact) ? $this->contact->email : 'no contact email on record', 'label' => ctrans('texts.email')];
        $data['$client_name'] = ['value' => $this->client->present()->name() ?: ' ', 'label' => ctrans('texts.client_name')];
        $data['$client.name'] = &$data['$client_name'];
        $data['$client'] = &$data['$client_name'];
        $data['$client.address1'] = &$data['$address1'];
        $data['$client.address2'] = &$data['$address2'];
        $data['$client_address'] = ['value' => $this->client->present()->address() ?: ' ', 'label' => ctrans('texts.address')];
        $data['$client.address'] = &$data['$client_address'];
        $data['$client.id_number'] = &$data['$id_number'];
        $data['$client.vat_number'] = &$data['$vat_number'];
        $data['$client.website'] = &$data['$website'];
        $data['$client.phone'] = &$data['$phone'];
        $data['$city_state_postal'] = ['value' => $this->client->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, false) ?: ' ', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = ['value' => $this->client->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.postal_city_state'] = &$data['$postal_city_state'];
        $data['$postal_city'] = ['value' => $this->client->present()->cityStateZip($this->client->city, null, $this->client->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city')];
        $data['$client.postal_city'] = &$data['$postal_city'];
        $data['$client.country'] = &$data['$country'];
        $data['$client.email'] = &$data['$email'];

        $data['$client.balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$outstanding'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$client_balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$paid_to_date'] = ['value' => Number::formatMoney($this->client->paid_to_date, $this->client), 'label' => ctrans('texts.paid_to_date')];

        $data['$contact.full_name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : '', 'label' => ctrans('texts.name')];
        $data['$contact.email'] = ['value' => isset($this->contact) ? $this->contact->email : '', 'label' => ctrans('texts.email')];
        $data['$contact.phone'] = ['value' => isset($this->contact) ? $this->contact->phone : '', 'label' => ctrans('texts.phone')];

        $data['$contact.name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : 'no contact name on record', 'label' => ctrans('texts.contact_name')];
        $data['$contact.first_name'] = ['value' => isset($this->contact) ? $this->contact->first_name : '', 'label' => ctrans('texts.first_name')];
        $data['$contact.last_name'] = ['value' => isset($this->contact) ? $this->contact->last_name : '', 'label' => ctrans('texts.last_name')];
        $data['$contact.custom1'] = ['value' => isset($this->contact) ? $this->contact->custom_value1 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom2'] = ['value' => isset($this->contact) ? $this->contact->custom_value2 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom3'] = ['value' => isset($this->contact) ? $this->contact->custom_value3 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom4'] = ['value' => isset($this->contact) ? $this->contact->custom_value4 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$firstName'] = &$data['$contact.first_name'];

        $data['$company.city_state_postal'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, false) ?: ' ', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.postal_city'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, null, $this->settings->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city')];
        $data['$company.name'] = ['value' => $this->company->present()->name() ?: ' ', 'label' => ctrans('texts.company_name')];
        $data['$company.address1'] = ['value' => $this->settings->address1 ?: ' ', 'label' => ctrans('texts.address1')];
        $data['$company.address2'] = ['value' => $this->settings->address2 ?: ' ', 'label' => ctrans('texts.address2')];
        $data['$company.city'] = ['value' => $this->settings->city ?: ' ', 'label' => ctrans('texts.city')];
        $data['$company.state'] = ['value' => $this->settings->state ?: ' ', 'label' => ctrans('texts.state')];
        $data['$company.postal_code'] = ['value' => $this->settings->postal_code ?: ' ', 'label' => ctrans('texts.postal_code')];
        //$data['$company.country'] = ['value' => $this->getCountryName(), 'label' => ctrans('texts.country')];
        $data['$company.phone'] = ['value' => $this->settings->phone ?: ' ', 'label' => ctrans('texts.phone')];
        $data['$company.email'] = ['value' => $this->settings->email ?: ' ', 'label' => ctrans('texts.email')];
        $data['$company.vat_number'] = ['value' => $this->settings->vat_number ?: ' ', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number'] = ['value' => $this->settings->id_number ?: ' ', 'label' => ctrans('texts.id_number')];
        $data['$company.website'] = ['value' => $this->settings->website ?: ' ', 'label' => ctrans('texts.website')];
        $data['$company.address'] = ['value' => $this->company->present()->address($this->settings) ?: ' ', 'label' => ctrans('texts.address')];

        $logo = $this->company->present()->logo($this->settings);

        $data['$company.logo'] = ['value' => $logo ?: ' ', 'label' => ctrans('texts.logo')];
        $data['$company_logo'] = &$data['$company.logo'];
        $data['$company1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];
        $data['$company2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company2', $this->settings->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company2')];
        $data['$company3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company3', $this->settings->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company3')];
        $data['$company4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company4', $this->settings->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company4')];

        $data['$view_link'] = ['value' => $this->buildViewButton($this->payment->getLink(), ctrans('texts.view_payment')), 'label' => ctrans('texts.view_payment')];
        $data['$view_button'] = &$data['$view_link'];
        $data['$viewButton'] = &$data['$view_link'];
        $data['$viewLink'] = &$data['$view_link'];
        $data['$paymentLink'] = &$data['$view_link'];
        $data['$portalButton'] = ['value' =>  $this->buildViewButton($this->payment->getPortalLink(), ctrans('texts.login')), 'label' => ''];
        $data['$portal_url'] = &$data['$portalButton'];

        $data['$view_url'] = ['value' => $this->payment->getLink(), 'label' => ctrans('texts.view_payment')];
        $data['$signature'] = ['value' => $this->settings->email_signature ?: ' ', 'label' => ''];
        $data['$emailSignature'] = &$data['$signature'];

        $data['$invoices'] = ['value' => $this->formatInvoices(), 'label' => ctrans('texts.invoices')];
        $data['$invoice_references'] = ['value' => $this->formatInvoiceReferences(), 'label' => ctrans('texts.invoices')];
        $data['$invoice'] = ['value' => $this->formatInvoice(), 'label' => ctrans('texts.invoice')];
        $data['$invoice.po_number'] = ['value' => $this->formatPoNumber(), 'label' => ctrans('texts.po_number')];
        $data['$poNumber'] = &$data['$invoice.po_number'];
        $data['$payment.status'] = ['value' => $this->payment->stringStatus($this->payment->status_id), 'label' => ctrans('texts.payment_status')];
        $data['$invoices.amount'] = ['value' => $this->formatInvoiceField('amount'), 'label' => ctrans('texts.invoices')];
        $data['$amount_paid'] = ['value' => '', 'label' => ctrans('texts.amount_paid')];

        $data['$invoices.balance'] = ['value' => $this->formatInvoiceField('balance'), 'label' => ctrans('texts.invoices')];
        $data['$invoices.due_date'] = ['value' => $this->formatInvoiceField('due_date'), 'label' => ctrans('texts.invoices')];
        $data['$invoices.po_number'] = ['value' => $this->formatInvoiceField('po_number'), 'label' => ctrans('texts.invoices')];
        $data['$date'] = ['value' => '', 'label' => ctrans('texts.date')];
        $data['$method'] = ['value' => '', 'label' => ctrans('texts.method')];
        $data['$transaction_reference'] = ['value' => '', 'label' => ctrans('texts.transaction_reference')];
        $data['$public_notes'] = ['value' => $this->client->public_notes, 'label' => ctrans('texts.public_notes')];
        $data['$receipt'] = ['value' => '', 'label' => ctrans('texts.receipt')];
        $data['$amount_paid'] = ['value' => '', 'label' => ctrans('texts.amount_paid')];
        $data['$refund'] = ['value' => '', 'label' => ctrans('texts.refund')];
        $data['$refunded'] = ['value' => '', 'label' => ctrans('texts.refunded')];
        $data['$reference'] = ['value' => '', 'label' => ctrans('texts.reference')];
        $data['$total'] = ['value' => '', 'label' => ctrans('texts.total')];
        $data['$history'] = ['value' => '', 'label' => ctrans('texts.history')];

        if ($this->payment->status_id == 4) {
            $data['$status_logo'] = ['value' => '<div class="stamp is-paid"> ' . ctrans('texts.paid') .'</div>', 'label' => ''];
        } else {
            $data['$status_logo'] = ['value' => '', 'label' => ''];
        }

        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

        return $data;
    }

    private function formatInvoiceField($field)
    {
        $invoicex = '';

        foreach ($this->payment->invoices as $invoice) {
            $invoice_field = $invoice->{$field};

            if (in_array($field, ['amount', 'balance'])) {
                $invoice_field = Number::formatMoney($invoice_field, $this->client);
            }

            if ($field == 'due_date') {
                $invoice_field = $this->translateDate($invoice_field, $this->client->date_format(), $this->client->locale());
            }

            $invoicex .= ctrans('texts.invoice_number_short') . "{$invoice->number} {$invoice_field}";
        }

        return $invoicex;
    }

    private function formatInvoice()
    {
        $invoice = '';

        if ($this->payment->invoices()->exists()) {
            $invoice = ctrans('texts.invoice_number_short').implode(',', $this->payment->invoices->pluck('number')->toArray());
        }

        return $invoice;
    }

    private function formatPoNumber()
    {
        $invoice = '';

        if ($this->payment->invoices()->exists()) {
            $invoice = ctrans('texts.po_number_short').implode(',', $this->payment->invoices->pluck('po_number')->toArray());
        }

        return $invoice;
    }

    private function formatInvoices()
    {
        $invoice_list = '<br><br>';

        foreach ($this->payment->invoices as $invoice) {
            $invoice_list .= ctrans('texts.invoice_number_short')." {$invoice->number} ".Number::formatMoney($invoice->pivot->amount, $this->client).'<br>';
        }

        return $invoice_list;
    }

    private function formatInvoiceReferences()
    {
        $invoice_list = '<br><br>';

        foreach ($this->payment->invoices as $invoice) {
            if (strlen($invoice->po_number) > 1) {
                $invoice_list .= ctrans('texts.po_number')." {$invoice->po_number} <br>";
            }

            $invoice_list .= ctrans('texts.invoice_number_short')." {$invoice->number} <br>";
            $invoice_list .= ctrans('texts.invoice_amount').' '.Number::formatMoney($invoice->pivot->amount, $this->client).'<br>';
            $invoice_list .= ctrans('texts.invoice_balance').' '.Number::formatMoney($invoice->fresh()->balance, $this->client).'<br>';
            $invoice_list .= '-----<br>';
        }

        return $invoice_list;
    }

    public function makeValues(): array
    {
        $data = [];

        $values = $this->makePaymentVariables();

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }

    /**
     * generateLabelsAndValues
     *
     * @return array
     */
    public function generateLabelsAndValues(): array
    {
        $data = [];

        $values = $this->makePaymentVariables();

        foreach ($values as $key => $value) {
            $data['values'][$key] = $value['value'];
            $data['labels'][$key.'_label'] = $value['label'];
        }

        return $data;
    }

    /**
     * buildViewButton
     *
     * @param  string $link
     * @param  string $text
     * @return string
     */
    private function buildViewButton(string $link, string $text): string
    {
        return '
<div>
<!--[if (gte mso 9)|(IE)]>
<table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
    <tr>
    <td align="center" valign="top">
        <![endif]-->        
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
        <tbody><tr>
        <td align="center" class="new_button" style="border-radius: 2px; background-color: '.$this->settings->primary_color.'">
            <a href="'. $link . '" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid '.$this->settings->primary_color.'; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
            <singleline label="cta button">'. $text .'</singleline>
            </a>
        </td>
        </tr>
        </tbody>
        </table>
<!--[if (gte mso 9)|(IE)]>
    </td>
    </tr>
</table>
<![endif]-->
</div>
        ';


        // return '
        //     <table border="0" cellspacing="0" cellpadding="0" align="center">
        //         <tr style="border: 0 !important; ">
        //             <td class="new_button" style="padding: 12px 18px 12px 18px; border-radius:5px;" align="center">
        //             <a href="'. $link .'" target="_blank" style="border: 0 !important;font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; display: inline-block;">'. $text .'</a>
        //             </td>
        //         </tr>
        //     </table>
        // ';
    }
}
