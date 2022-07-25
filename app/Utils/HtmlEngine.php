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

namespace App\Utils;

use App\Helpers\SwissQr\SwissQrGenerator;
use App\Models\Country;
use App\Models\CreditInvitation;
use App\Models\GatewayType;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\transformTranslations;
use Exception;
use Illuminate\Support\Facades\App;

class HtmlEngine
{
    use MakesDates;

    public $entity;

    public $invitation;

    public $client;

    public $contact;

    public $company;

    public $settings;

    public $entity_calc;

    public $entity_string;

    private $helpers;

    public function __construct($invitation)
    {
        $this->invitation = $invitation;

        $this->entity_string = $this->resolveEntityString();

        $this->entity = $invitation->{$this->entity_string};

        $this->company = $invitation->company;

        $this->contact = $invitation->contact->load('client');
        
        $this->client = $this->contact->client->load('company','country');
        
        $this->entity->load('client');

        $this->settings = $this->client->getMergedSettings();

        $this->entity_calc = $this->entity->calc();

        $this->helpers = new Helpers();
    }


    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function resolveEntityString()
    {
        switch ($this->invitation) {
            case ($this->invitation instanceof InvoiceInvitation):
                return 'invoice';
                break;
            case ($this->invitation instanceof CreditInvitation):
                return 'credit';
                break;
            case ($this->invitation instanceof QuoteInvitation):
                return 'quote';
                break;
            case ($this->invitation instanceof RecurringInvoiceInvitation):
                return 'recurring_invoice';
                break;
            default:
                # code...
                break;
        }
    }

    public function buildEntityDataArray() :array
    {
        if (! $this->client->currency()) {
            throw new Exception(debug_backtrace()[1]['function'], 1);
            exit;
        }

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->contact->preferredLocale());
        $t->replace(Ninja::transformTranslations($this->settings));

        $data = [];
        //$data['<html>'] = ['value' => '<html dir="rtl">', 'label' => ''];
        $data['$global_margin'] = ['value' => '6.35mm', 'label' => ''];
        $data['$tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$app_url'] = ['value' => $this->generateAppUrl(), 'label' => ''];
        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$total_tax_labels'] = ['value' => $this->totalTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$total_tax_values'] = ['value' => $this->totalTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_labels'] = ['value' => $this->lineTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_values'] = ['value' => $this->lineTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.date')];

        $data['$invoice.date'] = &$data['$date'];
        $data['$invoiceDate'] = &$data['$date'];
        $data['$due_date'] = ['value' => $this->translateDate($this->entity->due_date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];

        $data['$partial_due_date'] = ['value' => $this->translateDate($this->entity->partial_due_date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];
        
        $data['$dueDate'] = &$data['$due_date'];

        $data['$payment_due'] = ['value' => $this->translateDate($this->entity->due_date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.payment_due')];
        $data['$invoice.due_date'] = &$data['$due_date'];
        $data['$invoice.number'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.invoice_number')];
        $data['$invoice.po_number'] = ['value' => $this->entity->po_number ?: '&nbsp;', 'label' => ctrans('texts.po_number')];
        $data['$poNumber'] = &$data['$invoice.po_number'];
        $data['$entity.datetime'] = ['value' => $this->formatDatetime($this->entity->created_at, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.date')];
        $data['$invoice.datetime'] = &$data['$entity.datetime'];
        $data['$quote.datetime'] = &$data['$entity.datetime'];
        $data['$credit.datetime'] = &$data['$entity.datetime'];
        $data['$payment_button'] = ['value' => '<a class="button" href="'.$this->invitation->getPaymentLink().'">'.ctrans('texts.pay_now').'</a>', 'label' => ctrans('texts.pay_now')];
        $data['$payment_link'] = ['value' => $this->invitation->getPaymentLink(), 'label' => ctrans('texts.pay_now')];


        if ($this->entity_string == 'invoice' || $this->entity_string == 'recurring_invoice') {
            $data['$entity'] = ['value' => '', 'label' => ctrans('texts.invoice')];
            $data['$number'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.invoice_number')];
            $data['$number_short'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.invoice_number_short')];
            $data['$entity.terms'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->terms), $this->client) ?: '', 'label' => ctrans('texts.invoice_terms')];
            $data['$terms'] = &$data['$entity.terms'];
            $data['$view_link'] = ['value' => '<a class="button" href="'.$this->invitation->getLink().'">'.ctrans('texts.view_invoice').'</a>', 'label' => ctrans('texts.view_invoice')];
            $data['$viewLink'] = &$data['$view_link'];
            $data['$viewButton'] = &$data['$view_link'];
            $data['$view_button'] = &$data['$view_link'];
            $data['$paymentButton'] = &$data['$payment_button'];
            $data['$view_url'] = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_invoice')];
            $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.invoice_date')];

            if($this->entity->project) {
                $data['$project.name'] = ['value' => $this->entity->project->name, 'label' => ctrans('texts.project_name')];
                $data['$invoice.project'] = &$data['$project.name'];
            }

            if($this->entity->vendor) {
                $data['$invoice.vendor'] = ['value' => $this->entity->vendor->present()->name(), 'label' => ctrans('texts.vendor_name')];
            }

            if(strlen($this->company->getSetting('qr_iban')) > 5 && strlen($this->company->getSetting('besr_id')) > 1)
            {
                try{
                    $data['$swiss_qr'] = ['value' => (new SwissQrGenerator($this->entity, $this->company))->run(), 'label' => ''];
                }
                catch(\Exception $e){
                    $data['$swiss_qr'] = ['value' => '', 'label' => ''];
                }
            }

        }

        if ($this->entity_string == 'quote') {
            $data['$entity'] = ['value' => '', 'label' => ctrans('texts.quote')];
            $data['$number'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.quote_number')];
            $data['$number_short'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.quote_number_short')];
            $data['$entity.terms'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->terms), $this->client) ?: '', 'label' => ctrans('texts.quote_terms')];
            $data['$terms'] = &$data['$entity.terms'];
            $data['$view_link'] = ['value' => '<a class="button" href="'.$this->invitation->getLink().'">'.ctrans('texts.view_quote').'</a>', 'label' => ctrans('texts.view_quote')];
            $data['$viewLink'] = &$data['$view_link'];
            $data['$viewButton'] = &$data['$view_link'];
            $data['$view_button'] = &$data['$view_link'];
            $data['$approveButton'] = ['value' => '<a class="button" href="'.$this->invitation->getLink().'">'.ctrans('texts.view_quote').'</a>', 'label' => ctrans('texts.approve')];
            $data['$view_url'] = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_quote')];
            $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.quote_date')];

            if($this->entity->project) {
                $data['$project.name'] = ['value' => $this->entity->project->name, 'label' => ctrans('texts.project_name')];
                $data['$invoice.project'] = &$data['$project.name'];
            }

            if($this->entity->vendor) {
                $data['$invoice.vendor'] = ['value' => $this->entity->vendor->present()->name(), 'label' => ctrans('texts.vendor_name')];
            }

        }

        if ($this->entity_string == 'credit') {
            $data['$entity'] = ['value' => '', 'label' => ctrans('texts.credit')];
            $data['$number'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.credit_number')];
            $data['$number_short'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.credit_number_short')];
            $data['$entity.terms'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->terms), $this->client) ?: '', 'label' => ctrans('texts.credit_terms')];
            $data['$terms'] = &$data['$entity.terms'];
            $data['$view_link'] = ['value' => '<a class="button" href="'.$this->invitation->getLink().'">'.ctrans('texts.view_credit').'</a>', 'label' => ctrans('texts.view_credit')];
            $data['$viewButton'] = &$data['$view_link'];
            $data['$view_button'] = &$data['$view_link'];
            $data['$viewLink'] = &$data['$view_link'];
            $data['$view_url'] = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_credit')];
            // $data['$view_link']          = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_credit')];
            $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.credit_date')];
        }

        $data['$portal_url'] = ['value' => $this->invitation->getPortalLink(), 'label' =>''];

        $data['$entity_number'] = &$data['$number'];
        $data['$invoice.discount'] = ['value' => Number::formatMoney($this->entity_calc->getTotalDiscount(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.discount')];
        $data['$discount'] = &$data['$invoice.discount'];
        $data['$subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getSubTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.subtotal')];
        $data['$gross_subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getGrossSubTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.subtotal')];

        if($this->entity->uses_inclusive_taxes)
            $data['$net_subtotal'] = ['value' => Number::formatMoney(($this->entity_calc->getSubTotal() - $this->entity->total_taxes - $this->entity_calc->getTotalDiscount()), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.net_subtotal')];
        else
            $data['$net_subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getSubTotal() - $this->entity_calc->getTotalDiscount(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.net_subtotal')];

        $data['$invoice.subtotal'] = &$data['$subtotal'];

        if ($this->entity->partial > 0) {
            $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->partial, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.partial_due')];
            $data['$balance_due_raw'] = ['value' => $this->entity->partial, 'label' => ctrans('texts.partial_due')];
            $data['$amount_raw'] = ['value' => $this->entity->partial, 'label' => ctrans('texts.partial_due')];
            $data['$due_date'] = ['value' => $this->translateDate($this->entity->partial_due_date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];

        } else {

            if($this->entity->status_id == 1){
                $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->amount, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.balance_due')];
                $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
            }
            else{
                $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->balance, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_raw'] = ['value' => $this->entity->balance, 'label' => ctrans('texts.balance_due')];
                $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
            }
        }

        $data['$quote.balance_due'] = &$data['$balance_due'];
        $data['$invoice.balance_due'] = &$data['$balance_due'];


        if ($this->entity_string == 'credit') {
            $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->balance, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.credit_balance')];
            $data['$balance_due_raw'] = ['value' => $this->entity->balance, 'label' => ctrans('texts.credit_balance')];
            $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];         
        }

        // $data['$balance_due'] = $data['$balance_due'];
        $data['$outstanding'] = &$data['$balance_due'];
        $data['$partial_due'] = ['value' => Number::formatMoney($this->entity->partial, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.partial_due')];
        $data['$partial'] = &$data['$partial_due'];

        $data['$total'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.total')];
        $data['$amount'] = &$data['$total'];
        $data['$amount_due'] = ['value' => &$data['$total']['value'], 'label' => ctrans('texts.amount_due')];
        $data['$quote.total'] = &$data['$total'];
        $data['$invoice.total'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.invoice_total')];
        $data['$invoice_total_raw'] = ['value' => $this->entity_calc->getTotal(), 'label' => ctrans('texts.invoice_total')];
        $data['$invoice.amount'] = &$data['$total'];
        $data['$quote.amount'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.quote_total')];
        $data['$credit.total'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.credit_total')];
        $data['$credit.number'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.credit_number')];
        $data['$credit.total'] = &$data['$credit.total'];
        $data['$credit.po_number'] = &$data['$invoice.po_number'];
        $data['$credit.date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.credit_date')];
        $data['$balance'] = ['value' => Number::formatMoney($this->entity_calc->getBalance(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.balance')];
        $data['$credit.balance'] = &$data['$balance'];

        $data['$invoice.balance'] = &$data['$balance'];
        $data['$taxes'] = ['value' => Number::formatMoney($this->entity_calc->getItemTotalTaxes(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.taxes')];
        $data['$invoice.taxes'] = &$data['$taxes'];

        $data['$user.name'] = ['value' => $this->entity->user->present()->name(), 'label' => ctrans('texts.name')];
        $data['$user.first_name'] = ['value' => $this->entity->user->first_name, 'label' => ctrans('texts.first_name')];
        $data['$user.last_name'] = ['value' => $this->entity->user->last_name, 'label' => ctrans('texts.last_name')];
        $data['$created_by_user'] = &$data['$user.name'];
        $data['$assigned_to_user'] = ['value' => $this->entity->assigned_user ? $this->entity->assigned_user->present()->name() : '', 'label' => ctrans('texts.name')];

        $data['$user_iban'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];

        $data['$invoice.custom1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice1', $this->entity->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice1')];
        $data['$invoice.custom2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice2', $this->entity->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice2')];
        $data['$invoice.custom3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice3', $this->entity->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice3')];
        $data['$invoice.custom4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice4', $this->entity->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice4')];
        $data['$invoice.public_notes'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->public_notes), $this->client) ?: '', 'label' => ctrans('texts.public_notes')];
        $data['$entity.public_notes'] = &$data['$invoice.public_notes'];
        $data['$public_notes'] = &$data['$invoice.public_notes'];
        $data['$notes'] = &$data['$public_notes'];

        $data['$entity_issued_to'] = ['value' => '', 'label' => ctrans("texts.{$this->entity_string}_issued_to")];
        $data['$your_entity'] = ['value' => '', 'label' => ctrans("texts.your_{$this->entity_string}")];

        $data['$quote.date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;', 'label' => ctrans('texts.quote_date')];
        $data['$quote.number'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.quote_number')];
        $data['$quote.po_number'] = &$data['$invoice.po_number'];
        $data['$quote.quote_number'] = &$data['$quote.number'];
        $data['$quote_no'] = &$data['$quote.number'];
        $data['$quote.quote_no'] = &$data['$quote.number'];
        $data['$quote.valid_until'] = ['value' => $this->translateDate($this->entity->due_date, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.valid_until')];
        $data['$valid_until'] = &$data['$quote.valid_until'];
        $data['$credit_amount'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.credit_amount')];
        $data['$credit_balance'] = ['value' => Number::formatMoney($this->entity->balance, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.credit_balance')];
        $data['$quote.custom1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice1', $this->entity->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice1')];
        $data['$quote.custom2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice2', $this->entity->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice2')];
        $data['$quote.custom3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice3', $this->entity->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice3')];
        $data['$quote.custom4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice4', $this->entity->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice4')];

        $data['$credit_number'] = &$data['$number'];
        $data['$credit_no'] = &$data['$number'];
        $data['$credit.credit_no'] = &$data['$number'];

        $data['$invoice_no'] = &$data['$number'];
        $data['$invoice.invoice_no'] = &$data['$number'];
        $data['$client1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client1', $this->client->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client1')];
        $data['$client2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client2', $this->client->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client2')];
        $data['$client3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client3', $this->client->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client3')];
        $data['$client4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client4', $this->client->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client4')];
        $data['$client.custom1'] = &$data['$client1'];
        $data['$client.custom2'] = &$data['$client2'];
        $data['$client.custom3'] = &$data['$client3'];
        $data['$client.custom4'] = &$data['$client4'];
        $data['$address1'] = ['value' => $this->client->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$address2'] = ['value' => $this->client->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$id_number'] = ['value' => $this->client->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$client.number'] = ['value' => $this->client->number ?: '&nbsp;', 'label' => ctrans('texts.number')];
        $data['$vat_number'] = ['value' => $this->client->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$website'] = ['value' => $this->client->present()->website() ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$phone'] = ['value' => $this->client->present()->phone() ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$country'] = ['value' => isset($this->client->country->name) ? ctrans('texts.country_' . $this->client->country->name) : '', 'label' => ctrans('texts.country')];
        $data['$country_2'] = ['value' => isset($this->client->country) ? $this->client->country->iso_3166_2 : '', 'label' => ctrans('texts.country')];
        $data['$email'] = ['value' => isset($this->contact) ? $this->contact->email : 'no contact email on record', 'label' => ctrans('texts.email')];
        
        if(str_contains($data['$email']['value'], 'example.com'))
            $data['$email'] = ['value' => '', 'label' => ctrans('texts.email')];

        $data['$client_name'] = ['value' => $this->entity->present()->clientName() ?: '&nbsp;', 'label' => ctrans('texts.client_name')];
        $data['$client.name'] = &$data['$client_name'];
        $data['$client'] = &$data['$client_name'];

        $data['$client.address1'] = &$data['$address1'];
        $data['$client.address2'] = &$data['$address2'];
        $data['$client_address'] = ['value' => $this->client->present()->address() ?: '&nbsp;', 'label' => ctrans('texts.address')];
        $data['$client.address'] = &$data['$client_address'];
        $data['$client.postal_code'] = ['value' => $this->client->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        $data['$client.public_notes'] = ['value' => $this->client->public_notes ?: '&nbsp;', 'label' => ctrans('texts.notes')];
        $data['$client.city'] = ['value' => $this->client->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$client.state'] = ['value' => $this->client->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$client.id_number'] = &$data['$id_number'];
        $data['$client.vat_number'] = &$data['$vat_number'];
        $data['$client.website'] = &$data['$website'];
        $data['$client.phone'] = &$data['$phone'];
        $data['$city_state_postal'] = ['value' => $this->entity->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = ['value' => $this->entity->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.postal_city_state'] = &$data['$postal_city_state'];
        $data['$client.country'] = &$data['$country'];
        $data['$client.email'] = &$data['$email'];
        
        $data['$client.billing_address'] = &$data['$client_address'];
        $data['$client.billing_address1'] = &$data['$client.address1'];
        $data['$client.billing_address2'] = &$data['$client.address2'];
        $data['$client.billing_city'] = &$data['$client.city'];
        $data['$client.billing_state'] = &$data['$client.state'];
        $data['$client.billing_postal_code'] = &$data['$client.postal_code'];
        $data['$client.billing_country'] = &$data['$client.country'];

        $data['$client.shipping_address'] = ['value' => $this->client->present()->shipping_address() ?: '&nbsp;', 'label' => ctrans('texts.shipping_address')];
        $data['$client.shipping_address1'] = ['value' => $this->client->shipping_address1 ?: '&nbsp;', 'label' => ctrans('texts.shipping_address1')];
        $data['$client.shipping_address2'] = ['value' => $this->client->shipping_address2 ?: '&nbsp;', 'label' => ctrans('texts.shipping_address2')];
        $data['$client.shipping_city'] = ['value' => $this->client->shipping_city ?: '&nbsp;', 'label' => ctrans('texts.shipping_city')];
        $data['$client.shipping_state'] = ['value' => $this->client->shipping_state ?: '&nbsp;', 'label' => ctrans('texts.shipping_state')];
        $data['$client.shipping_postal_code'] = ['value' => $this->client->shipping_postal_code ?: '&nbsp;', 'label' => ctrans('texts.shipping_postal_code')];
        $data['$client.shipping_country'] = ['value' => isset($this->client->shipping_country->name) ? ctrans('texts.country_' . $this->client->shipping_country->name) : '', 'label' => ctrans('texts.shipping_country')];

        $data['$client.currency'] = ['value' => $this->client->currency()->code, 'label' => ''];

        $data['$client.lang_2'] = ['value' => optional($this->client->language())->locale, 'label' => ''];

        $data['$client.balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$client_balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$paid_to_date'] = ['value' => Number::formatMoney($this->entity->paid_to_date, $this->client), 'label' => ctrans('texts.paid_to_date')];

        $data['$contact.full_name'] = ['value' => $this->contact->present()->name(), 'label' => ctrans('texts.name')];
        $data['$contact'] = &$data['$contact.full_name'];

        $data['$contact.email'] = &$data['$email'];
        $data['$contact.phone'] = ['value' => $this->contact->phone, 'label' => ctrans('texts.phone')];

        $data['$contact.name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : $this->client->present()->name(), 'label' => ctrans('texts.contact_name')];
        $data['$contact.first_name'] = ['value' => isset($this->contact) ? $this->contact->first_name : '', 'label' => ctrans('texts.first_name')];
        $data['$firstName'] = &$data['$contact.first_name'];

        $data['$contact.last_name'] = ['value' => isset($this->contact) ? $this->contact->last_name : '', 'label' => ctrans('texts.last_name')];

        $data['$portal_button'] = ['value' => '<a class="button" href="'.$this->contact->getLoginLink().'?client_hash='.$this->client->client_hash.'">'.ctrans('texts.view_client_portal').'</a>', 'label' => ctrans('view_client_portal')];
        $data['$contact.portal_button'] = &$data['$portal_button'];

        $data['$contact.custom1'] = ['value' => isset($this->contact) ? $this->contact->custom_value1 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom2'] = ['value' => isset($this->contact) ? $this->contact->custom_value2 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact2')];
        $data['$contact.custom3'] = ['value' => isset($this->contact) ? $this->contact->custom_value3 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact3')];
        $data['$contact.custom4'] = ['value' => isset($this->contact) ? $this->contact->custom_value4 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact4')];

        $data['$company.city_state_postal'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.name'] = ['value' => $this->settings->name ?: ctrans('texts.untitled_account'), 'label' => ctrans('texts.company_name')];
        $data['$account'] = &$data['$company.name'];

        $data['$company.address1'] = ['value' => $this->settings->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$company.address2'] = ['value' => $this->settings->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$company.city'] = ['value' => $this->settings->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$company.state'] = ['value' => $this->settings->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$company.postal_code'] = ['value' => $this->settings->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        $data['$company.country'] = ['value' => $this->getCountryName(), 'label' => ctrans('texts.country')];
        $data['$company.country_2'] = ['value' => $this->getCountryCode(), 'label' => ctrans('texts.country')];
        $data['$company.phone'] = ['value' => $this->settings->phone ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$company.email'] = ['value' => $this->settings->email ?: '&nbsp;', 'label' => ctrans('texts.email')];
        $data['$company.vat_number'] = ['value' => $this->settings->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number'] = ['value' => $this->settings->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$company.website'] = ['value' => $this->settings->website ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$company.address'] = ['value' => $this->company->present()->address($this->settings) ?: '&nbsp;', 'label' => ctrans('texts.address')];

        $data['$signature'] = ['value' => $this->settings->email_signature ?: '&nbsp;', 'label' => ''];
        $data['$emailSignature'] = &$data['$signature'];

        $data['$spc_qr_code'] = ['value' => $this->company->present()->getSpcQrCode($this->client->currency()->code, $this->entity->number, $this->entity->balance, $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client)), 'label' => ''];

        $logo = $this->company->present()->logo_base64($this->settings);

        $data['$company.logo'] = ['value' => $logo ?: '&nbsp;', 'label' => ctrans('texts.logo')];
        $data['$company_logo'] = &$data['$company.logo'];
        $data['$company1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];
        $data['$company2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company2', $this->settings->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company2')];
        $data['$company3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company3', $this->settings->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company3')];
        $data['$company4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company4', $this->settings->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company4')];

        $data['$company.custom1'] = &$data['$company1'];
        $data['$company.custom2'] = &$data['$company2'];
        $data['$company.custom3'] = &$data['$company3'];
        $data['$company.custom4'] = &$data['$company4'];

        $data['$custom_surcharge1'] = ['value' => Number::formatMoney($this->entity->custom_surcharge1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge1')];
        $data['$custom_surcharge2'] = ['value' => Number::formatMoney($this->entity->custom_surcharge2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge2')];
        $data['$custom_surcharge3'] = ['value' => Number::formatMoney($this->entity->custom_surcharge3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge3')];
        $data['$custom_surcharge4'] = ['value' => Number::formatMoney($this->entity->custom_surcharge4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge4')];

        $data['$product.item'] = ['value' => '', 'label' => ctrans('texts.item')];
        $data['$product.date'] = ['value' => '', 'label' => ctrans('texts.date')];
        $data['$product.discount'] = ['value' => '', 'label' => ctrans('texts.discount')];
        $data['$product.product_key'] = ['value' => '', 'label' => ctrans('texts.product_key')];
        $data['$product.description'] = ['value' => '', 'label' => ctrans('texts.description')];
        $data['$product.unit_cost'] = ['value' => '', 'label' => ctrans('texts.unit_cost')];
        $data['$product.quantity'] = ['value' => '', 'label' => ctrans('texts.quantity')];
        $data['$product.tax_name1'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name2'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name3'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.line_total'] = ['value' => '', 'label' => ctrans('texts.line_total')];
        $data['$product.gross_line_total'] = ['value' => '', 'label' => ctrans('texts.gross_line_total')];
        $data['$product.description'] = ['value' => '', 'label' => ctrans('texts.description')];
        $data['$product.unit_cost'] = ['value' => '', 'label' => ctrans('texts.unit_cost')];
        $data['$product.product1'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product1')];
        $data['$product.product2'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product2')];
        $data['$product.product3'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product3')];
        $data['$product.product4'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product4')];

        $data['$task.date'] = ['value' => '', 'label' => ctrans('texts.date')];
        $data['$task.discount'] = ['value' => '', 'label' => ctrans('texts.discount')];
        $data['$task.service'] = ['value' => '', 'label' => ctrans('texts.service')];
        $data['$task.description'] = ['value' => '', 'label' => ctrans('texts.description')];
        $data['$task.rate'] = ['value' => '', 'label' => ctrans('texts.rate')];
        $data['$task.cost'] = ['value' => '', 'label' => ctrans('texts.rate')];
        $data['$task.hours'] = ['value' => '', 'label' => ctrans('texts.hours')];
        $data['$task.tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name1'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name2'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name3'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.line_total'] = ['value' => '', 'label' => ctrans('texts.line_total')];
        $data['$task.gross_line_total'] = ['value' => '', 'label' => ctrans('texts.gross_line_total')];
        $data['$task.service'] = ['value' => '', 'label' => ctrans('texts.service')];
        $data['$task.task1'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task1')];
        $data['$task.task2'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task2')];
        $data['$task.task3'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task3')];
        $data['$task.task4'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task4')];

        if ($this->settings->signature_on_pdf) {
            $data['$contact.signature'] = ['value' => $this->invitation->signature_base64, 'label' => ctrans('texts.signature')];
        } else {
            $data['$contact.signature'] = ['value' => '', 'label' => ''];
        }

        $data['$thanks'] = ['value' => '', 'label' => ctrans('texts.thanks')];
        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$details'] = ['value' => '', 'label' => ctrans('texts.details')];

        $data['_rate1'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['_rate2'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['_rate3'] = ['value' => '', 'label' => ctrans('texts.tax')];

        $data['$font_size'] = ['value' => $this->settings->font_size . 'px', 'label' => ''];
        $data['$font_name'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['name'], 'label' => ''];
        $data['$font_url'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['url'], 'label' => ''];

        $data['$invoiceninja.whitelabel'] = ['value' => 'https://raw.githubusercontent.com/invoiceninja/invoiceninja/v5-develop/public/images/new_logo.png', 'label' => ''];

        $data['$primary_color'] = ['value' => $this->settings->primary_color, 'label' => ''];
        $data['$secondary_color'] = ['value' => $this->settings->secondary_color, 'label' => ''];

        $data['$item'] = ['value' => '', 'label' => ctrans('texts.item')];
        $data['$description'] = ['value' => '', 'label' => ctrans('texts.description')];

        //$data['$entity_footer'] = ['value' => $this->client->getSetting("{$this->entity_string}_footer"), 'label' => ''];
        $data['$entity_footer'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->footer), $this->client), 'label' => ''];
        $data['$footer'] = &$data['$entity_footer'];
        
        $data['$page_size'] = ['value' => $this->settings->page_size, 'label' => ''];
        $data['$page_layout'] = ['value' => property_exists($this->settings, 'page_layout') ? $this->settings->page_layout : 'Portrait', 'label' => ''];

        $data['$tech_hero_image'] = ['value' => asset('images/pdf-designs/tech-hero-image.jpg'), 'label' => ''];
        $data['$autoBill'] = ['value' => ctrans('texts.auto_bill_notification_placeholder'), 'label' => ''];
        $data['$auto_bill'] = &$data['$autoBill'];

        /*Payment Aliases*/
        $data['$paymentLink'] = &$data['$payment_link'];
        $data['$payment_url'] = &$data['$payment_link'];
        $data['$portalButton'] = &$data['$portal_button'];
        
        $data['$dir'] = ['value' => in_array(optional($this->client->language())->locale, ['ar', 'he']) ? 'rtl' : 'ltr', 'label' => ''];
        $data['$dir_text_align'] = ['value' => in_array(optional($this->client->language())->locale, ['ar', 'he']) ? 'right' : 'left', 'label' => ''];

        $data['$payment.date'] = ['value' => '&nbsp;', 'label' => ctrans('texts.payment_date')];
        $data['$method'] = ['value' => '&nbsp;', 'label' => ctrans('texts.method')];

        $data['$statement_amount'] = ['value' => '', 'label' => ctrans('texts.amount')];
        $data['$statement'] = ['value' => '', 'label' => ctrans('texts.statement')];

        $data['$entity_images'] = ['value' => $this->generateEntityImagesMarkup(), 'label' => ''];

        $data['$payments'] = ['value' => '', 'label' => ctrans('texts.payments')];

        if ($this->entity_string == 'invoice' && $this->entity->payments()->exists()) {
            
            $payment_list = '<br><br>';

            foreach ($this->entity->payments as $payment) {
                $payment_list .= ctrans('texts.payment_subject') . ": " . $this->formatDate($payment->date, $this->client->date_format()) . " :: " . Number::formatMoney($payment->amount, $this->client) ." :: ". GatewayType::getAlias($payment->gateway_type_id) . "<br>";
            }

            $data['$payments'] = ['value' => $payment_list, 'label' => ctrans('texts.payments')];
        }

        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

        return $data;
    }

    public function makeValues() :array
    {
        $data = [];

        $values = $this->buildEntityDataArray();

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }

    public function generateLabelsAndValues()
    {
        $data = [];

        $values = $this->buildEntityDataArray();

        foreach ($values as $key => $value) {
            $data['values'][$key] = $value['value'];
            $data['labels'][$key.'_label'] = $value['label'];
        }

        return $data;
    }

    private function totalTaxLabels() :string
    {
        $data = '';

        if (! $this->entity_calc->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
            $data .= '<span>'.$tax['name'].'</span>';
        }

        return $data;
    }

    private function totalTaxValues() :string
    {
        $data = '';

        if (! $this->entity_calc->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $this->client).'</span>';
        }

        return $data;
    }

    private function lineTaxLabels() :string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.$tax['name'].'</span>';
        }

        return $data;
    }

    private function getCountryName() :string
    {
        $country = Country::find($this->settings->country_id);

        if ($country) {
            return ctrans('texts.country_' . $country->name);
        }

        return '&nbsp;';
    }


    private function getCountryCode() :string
    {
        $country = Country::find($this->settings->country_id);

        if($country)
            return $country->iso_3166_2;
        // if ($country) {
        //     return ctrans('texts.country_' . $country->iso_3166_2);
        // }

        return '&nbsp;';
    }
    /**
     * Due to the way we are compiling the blade template we
     * have no ability to iterate, so in the case
     * of line taxes where there are multiple rows,
     * we use this function to format a section of rows.
     *
     * @return string a collection of <tr> rows with line item
     * aggregate data
     */
    private function makeLineTaxes() :string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<tr class="line_taxes">';
            $data .= '<td>'.$tax['name'].'</td>';
            $data .= '<td>'.Number::formatMoney($tax['total'], $this->client).'</td></tr>';
        }

        return $data;
    }

    private function lineTaxValues() :string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $this->client).'</span>';
        }

        return $data;
    }

    private function makeTotalTaxes() :string
    {
        $data = '';

        if (! $this->entity_calc->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
            $data .= '<tr>';
            $data .= '<td colspan="{ count($this->entity->company->settings->pdf_variables->total_columns) - 2 }"></td>';
            $data .= '<td>'.$tax['name'].'</td>';
            $data .= '<td>'.Number::formatMoney($tax['total'], $this->client).'</td></tr>';
        }

        return $data;
    }

    private function parseLabelsAndValues($labels, $values, $section) :string
    {
        $section = strtr($section, $labels);

        return strtr($section, $values);
    }

    /*
    | Ensures the URL doesn't have duplicated trailing slash
    */
    public function generateAppUrl()
    {
        //return rtrim(config('ninja.app_url'), "/");
        return config('ninja.app_url');
    }

    /**
     * Builds CSS to assist with the generation
     * of Repeating headers and footers on the PDF.
     * @return string The css string
     */
    private function generateCustomCSS() :string
    {
        $header_and_footer = '
.header, .header-space {
  height: 160px;
}

.footer, .footer-space {
  height: 160px;
}

.footer {
  position: fixed;
  bottom: 0;
  width: 100%;
}

.header {
  position: fixed;
  top: 0mm;
  width: 100%;
}

@media print {
   thead {display: table-header-group;}
   tfoot {display: table-footer-group;}
   button {display: none;}
   body {margin: 0;}
}';

        $header = '
.header, .header-space {
  height: 160px;
}

.header {
  position: fixed;
  top: 0mm;
  width: 100%;
}

@media print {
   thead {display: table-header-group;}
   button {display: none;}
   body {margin: 0;}
}';

        $footer = '

.footer, .footer-space {
  height: 160px;
}

.footer {
  position: fixed;
  bottom: 0;
  width: 100%;
}

@media print {
   tfoot {display: table-footer-group;}
   button {display: none;}
   body {margin: 0;}
}';
        $css = '';

        if ($this->settings->all_pages_header && $this->settings->all_pages_footer) {
            $css .= $header_and_footer;
        } elseif ($this->settings->all_pages_header && ! $this->settings->all_pages_footer) {
            $css .= $header;
        } elseif (! $this->settings->all_pages_header && $this->settings->all_pages_footer) {
            $css .= $footer;
        }

        $css .= '
.page {
  page-break-after: always;
}

@page {
  margin: 0mm
}

html {
        ';

        $css .= 'font-size:'.$this->settings->font_size.'px;';
//        $css .= 'font-size:14px;';

        $css .= '}';

        return $css;
    }

    /**
     * Generate markup for HTML images on entity.
     * 
     * @return string|void 
     */
    protected function generateEntityImagesMarkup()
    {
        if ($this->client->getSetting('embed_documents') === false) {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $container =  $dom->createElement('div');
        $container->setAttribute('style', 'display:grid; grid-auto-flow: row; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr);');

        foreach ($this->entity->documents as $document) {
            if (!$document->isImage()) {
                continue;
            }

            $image = $dom->createElement('img');

            $image->setAttribute('src', $document->generateUrl());
            $image->setAttribute('style', 'max-height: 100px; margin-top: 20px;');

            $container->appendChild($image);
        }

        $dom->appendChild($container);

        return $dom->saveHTML();
    }
}
