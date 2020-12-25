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

namespace App\Mail\Engine;

use App\DataMapper\EmailTemplateDefaults;
use App\Utils\Helpers;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;

class PaymentEmailEngine extends BaseEmailEngine
{
    use MakesDates;

    public $client;

    public $payment;

    public $template_data;

    public $settings;

    public $company;

    public $contact;

    private $helpers;
    
    public function __construct($payment, $contact, $template_data = null)
    {
        $this->payment = $payment;
        $this->company = $payment->company;
        $this->client = $payment->client;
        $this->contact = $contact ?: $this->client->primary_contact()->first();
        $this->settings = $this->client->getMergedSettings();
        $this->template_data = $template_data;
        $this->helpers = new Helpers();
    }

    public function build()
    {
        if (is_array($this->template_data) &&  array_key_exists('body', $this->template_data) && strlen($this->template_data['body']) > 0) {
            $body_template = $this->template_data['body'];
        } elseif (strlen($this->client->getSetting('email_template_payment')) > 0) {
            $body_template = $this->client->getSetting('email_template_payment');
        } else {
            $body_template = EmailTemplateDefaults::getDefaultTemplate('email_template_payment', $this->client->locale());
        }

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.payment_message',
                ['amount' => $payment->amount, 'company' => $payment->company->present()->name()],
                null,
                $this->client->locale()
            );
        }

        if (is_array($this->template_data) &&  array_key_exists('subject', $this->template_data) && strlen($this->template_data['subject']) > 0) {
            $subject_template = $this->template_data['subject'];
        } elseif (strlen($this->client->getSetting('email_subject_payment')) > 0) {
            $subject_template = $this->client->getSetting('email_subject_payment');
        } else {
            $subject_template = EmailTemplateDefaults::getDefaultTemplate('email_subject_payment', $this->client->locale());
        }

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans(
                'texts.payment_subject',
                ['number' => $payment->number, 'company' => $payment->company->present()->name()],
                null,
                $this->client->locale()
            );
        }

        $this->setTemplate($this->client->getSetting('email_style'))
            ->setContact($this->contact)
            ->setVariables($this->makeValues())
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter('')
            ->setViewLink('')
            ->setViewText('');

        return $this;
    }


    private function makePaymentVariables()
    {
        $data = [];

        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$number'] = ['value' => $this->payment->number ?: '&nbsp;', 'label' => ctrans('texts.payment_number')];
        $data['$payment.number'] = &$data['$number'];
        $data['$entity'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$payment.amount'] = ['value' => Number::formatMoney($this->payment->amount, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.amount')];
        $data['$amount'] = &$data['$payment.amount'];
        $data['$payment.date'] = ['value' => $this->formatDate($this->payment->date, $this->client->date_format()), 'label' => ctrans('texts.payment_date')];
        $data['$transaction_reference'] = ['value' => $this->payment->transaction_reference, 'label' => ctrans('texts.transaction_reference')];
        $data['$public_notes'] = ['value' => $this->payment->public_notes, 'label' => ctrans('texts.notes')];

        $data['$payment1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment1', $this->payment->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment1')];
        $data['$payment2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment2', $this->payment->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment2')];
        $data['$payment3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment3', $this->payment->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment3')];
        $data['$payment4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment4', $this->payment->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment4')];
        // $data['$type'] = ['value' => $this->payment->type->name ?: '', 'label' => ctrans('texts.payment_type')];

        $data['$client1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client1', $this->client->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client1')];
        $data['$client2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client2', $this->client->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client2')];
        $data['$client3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client3', $this->client->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client3')];
        $data['$client4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client4', $this->client->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client4')];
        $data['$address1'] = ['value' => $this->client->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$address2'] = ['value' => $this->client->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$id_number'] = ['value' => $this->client->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$vat_number'] = ['value' => $this->client->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$website'] = ['value' => $this->client->present()->website() ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$phone'] = ['value' => $this->client->present()->phone() ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$country'] = ['value' => isset($this->client->country->name) ? $this->client->country->name : '', 'label' => ctrans('texts.country')];
        $data['$email'] = ['value' => isset($this->contact) ? $this->contact->email : 'no contact email on record', 'label' => ctrans('texts.email')];
        $data['$client_name'] = ['value' => $this->client->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.client_name')];
        $data['$client.name'] = &$data['$client_name'];
        $data['$client.address1'] = &$data['$address1'];
        $data['$client.address2'] = &$data['$address2'];
        $data['$client_address'] = ['value' => $this->client->present()->address() ?: '&nbsp;', 'label' => ctrans('texts.address')];
        $data['$client.address'] = &$data['$client_address'];
        $data['$client.id_number'] = &$data['$id_number'];
        $data['$client.vat_number'] = &$data['$vat_number'];
        $data['$client.website'] = &$data['$website'];
        $data['$client.phone'] = &$data['$phone'];
        $data['$city_state_postal'] = ['value' => $this->client->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = ['value' => $this->client->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.postal_city_state'] = &$data['$postal_city_state'];
        $data['$client.country'] = &$data['$country'];
        $data['$client.email'] = &$data['$email'];

        $data['$client.balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$outstanding'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$client_balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$paid_to_date'] = ['value' => Number::formatMoney($this->client->paid_to_date, $this->client), 'label' => ctrans('texts.paid_to_date')];

        $data['$contact.full_name'] = ['value' => $this->contact->present()->name(), 'label' => ctrans('texts.name')];
        $data['$contact.email'] = ['value' => $this->contact->email, 'label' => ctrans('texts.email')];
        $data['$contact.phone'] = ['value' => $this->contact->phone, 'label' => ctrans('texts.phone')];

        $data['$contact.name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : 'no contact name on record', 'label' => ctrans('texts.contact_name')];
        $data['$contact.first_name'] = ['value' => isset($this->contact) ? $this->contact->first_name : '', 'label' => ctrans('texts.first_name')];
        $data['$contact.last_name'] = ['value' => isset($this->contact) ? $this->contact->last_name : '', 'label' => ctrans('texts.last_name')];
        $data['$contact.custom1'] = ['value' => isset($this->contact) ? $this->contact->custom_value1 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom2'] = ['value' => isset($this->contact) ? $this->contact->custom_value2 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom3'] = ['value' => isset($this->contact) ? $this->contact->custom_value3 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom4'] = ['value' => isset($this->contact) ? $this->contact->custom_value4 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];

        $data['$company.city_state_postal'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.name'] = ['value' => $this->company->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.company_name')];
        $data['$company.address1'] = ['value' => $this->settings->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$company.address2'] = ['value' => $this->settings->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$company.city'] = ['value' => $this->settings->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$company.state'] = ['value' => $this->settings->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$company.postal_code'] = ['value' => $this->settings->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        //$data['$company.country'] = ['value' => $this->getCountryName(), 'label' => ctrans('texts.country')];
        $data['$company.phone'] = ['value' => $this->settings->phone ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$company.email'] = ['value' => $this->settings->email ?: '&nbsp;', 'label' => ctrans('texts.email')];
        $data['$company.vat_number'] = ['value' => $this->settings->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number'] = ['value' => $this->settings->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$company.website'] = ['value' => $this->settings->website ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$company.address'] = ['value' => $this->company->present()->address($this->settings) ?: '&nbsp;', 'label' => ctrans('texts.address')];

        $logo = $this->company->present()->logo($this->settings);

        $data['$company.logo'] = ['value' => $logo ?: '&nbsp;', 'label' => ctrans('texts.logo')];
        $data['$company_logo'] = &$data['$company.logo'];
        $data['$company1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];
        $data['$company2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company2', $this->settings->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company2')];
        $data['$company3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company3', $this->settings->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company3')];
        $data['$company4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company4', $this->settings->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company4')];

        $data['$view_link'] = ['value' => '<a href="'.$this->payment->getLink().'">'.ctrans('texts.view_payment').'</a>', 'label' => ctrans('texts.view_payment')];
        $data['$view_url'] = ['value' => $this->payment->getLink(), 'label' => ctrans('texts.view_payment')];

        $data['$invoices'] = ['value' => $this->formatInvoices(), 'label' => ctrans('texts.invoices')];

        return $data;
    }

    private function formatInvoices()
    {
        $invoice_list = '';

        foreach ($this->payment->invoices as $invoice) {
            $invoice_list .= ctrans('texts.invoice_number_short') . " {$invoice->number} - " . Number::formatMoney($invoice->pivot->amount, $this->client) . "<br>";
        }

        return $invoice_list;
    }

    private function makeCustomField($field) :string
    {
        $custom_fields = $this->company->custom_fields;

        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};

            $custom_field_parts = explode('|', $custom_field);

            return $custom_field_parts[0];
        }

        return '';
    }

    public function makeValues() :array
    {
        $data = [];

        $values = $this->makePaymentVariables();

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }
}
