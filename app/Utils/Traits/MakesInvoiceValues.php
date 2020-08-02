<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\Models\Country;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Utils\Number;

/**
 * Class MakesInvoiceValues
 * @package App\Utils\Traits
 */
trait MakesInvoiceValues
{
    /**
     * Master list of columns used
     * for invoice tables
     * @var array
     */
    private static $master_columns = [
        'date',
        'discount',
        'product_key',
        'notes',
        'cost',
        'quantity',
        'tax_name1',
        'tax_name2',
        'tax_name3',
        'line_total',
        'custom_label1',
        'custom_label2',
        'custom_label3',
        'custom_label4',
    ];


    private static $custom_label_fields = [
        'invoice1',
        'invoice2',
        'invoice3',
        'invoice4',
        'surcharge1',
        'surcharge2',
        'surcharge3',
        'surcharge4',
        'client1',
        'client2',
        'client3',
        'client4',
        'contact1',
        'contact2',
        'contact3',
        'contact4',
        'company1',
        'company2',
        'company3',
        'company4',
    ];

    public function makeCustomField($field) :string
    {
        $custom_fields = $this->company->custom_fields;

        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};
            $custom_field_parts = explode("|", $custom_field);

            return $custom_field_parts[0];
        }

        return '';
    }

    private function findCustomType($field)
    {
        $custom_fields = $this->company->custom_fields;

        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};
            $custom_field_parts = explode("|", $custom_field);

            return $custom_field_parts[1];
        }

        return '';
    }

    /**
     * This method produces the key /value pairs for
     * custom fields
     *
     * We need to explode the field name and search for the |
     * we split on the pipe, the first value is the field name
     * and the second is the field _type_
     *
     * We transform the $value depending the $field type
     *
     * @param  string $field The full field name
     * @param  string $value The custom value
     * @return array         The key value pair
     */
    private function makeCustomFieldKeyValuePair($field, $value)
    {
        if ($this->findCustomType($field) == 'date') {
            $value = $this->formatDate($value, $this->client->date_format());
        } elseif ($this->findCustomType($field) == 'switch') {
            $value = ctrans('texts.'.$value);
        }

        if (!$value) {
            $value = '';
        }

        return ['value' => $value, 'field' => $this->makeCustomField($field)];
    }

    public function makeLabels($contact = null) :array
    {
        $data = [];

        $values = $this->makeLabelsAndValues($contact);

        foreach ($values as $key => $value) {
            $data[$key.'_label'] = $value['label'];
        }

        return $data;
    }

    /**
     * Transforms all placeholders
     * to invoice values
     *
     * @return array returns an array
     * of keyed labels (appended with _label)
     */
    public function makeValues($contact = null) :array
    {
        $data = [];

        $values = $this->makeLabelsAndValues($contact);

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }

    public function buildLabelsAndValues($contact) 
    {
        $data = [];

        $values = $this->makeLabelsAndValues($contact);
    
        foreach ($values as $key => $value) {
            $data['values'][$key] = $value['value'];
            $data['labels'][$key.'_label'] = $value['label'];
        }

        return $data;
    }

    private function makeLabelsAndValues($contact = null) :array
    {
        if (!$this->client->currency() || !$this->client) {
            throw new \Exception(debug_backtrace()[1]['function'], 1);
            exit;
        }
        
        $settings = $this->client->getMergedSettings();

        if (!$contact) {
            $contact = $this->client->primary_contact()->first();
        }

        $calc = $this->calc();
        $invitation = $this->invitations->where('client_contact_id', $contact->id)->first();

        $data = [];
        $data['$tax']                    = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$app_url']                = ['value' => $this->generateAppUrl(), 'label' => ''];
        $data['$from']                   = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to']                     = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$total_tax_labels']       = ['value' => $this->totalTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$total_tax_values']       = ['value' => $this->totalTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_labels']        = ['value' => $this->lineTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_values']        = ['value' => $this->lineTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$date']                   = ['value' => $this->date ?: '&nbsp;', 'label' => ctrans('texts.date')];
        //$data['$invoice_date']           = ['value' => $this->date ?: '&nbsp;', 'label' => ctrans('texts.invoice_date')];
        $data['$invoice.date']           = &$data['$date'];
        $data['$invoice.due_date']       = ['value' => $this->due_date ?: '&nbsp;', 'label' => ctrans('texts.due_date')];
        $data['$due_date']               = &$data['$invoice.due_date'];
        $data['$invoice.number']         = ['value' => $this->number ?: '&nbsp;', 'label' => ctrans('texts.invoice_number')];
        $data['$invoice.po_number']      = ['value' => $this->po_number ?: '&nbsp;', 'label' => ctrans('texts.po_number')];
        $data['$line_taxes']             = ['value' => $this->makeLineTaxes() ?: '&nbsp;', 'label' => ctrans('texts.taxes')];
        $data['$invoice.line_taxes']     = &$data['$line_taxes'];
        $data['$total_taxes']            = ['value' => $this->makeTotalTaxes() ?: '&nbsp;', 'label' => ctrans('texts.taxes')];
        $data['$invoice.total_taxes']    = &$data['$total_taxes'];

        if ($this instanceof Invoice) {
            $data['$entity_label']       = ['value' => '', 'label' => ctrans('texts.invoice')];
            $data['$number']             = ['value' => $this->number ?: '&nbsp;', 'label' => ctrans('texts.invoice_number')];
            $data['$entity.terms']       = ['value' => $this->terms ?: '&nbsp;', 'label' => ctrans('texts.invoice_terms')];
            $data['$terms']              = &$data['$entity.terms'];
            $data['$view_link']          = ['value' => '<a href="' .$invitation->getLink() .'">'. ctrans('texts.view_invoice').'</a>', 'label' => ctrans('texts.view_invoice')];
        }

        if ($this instanceof Quote) {
            $data['$entity_label']       = ['value' => '', 'label' => ctrans('texts.quote')];
            $data['$number']             = ['value' => $this->number ?: '&nbsp;', 'label' => ctrans('texts.quote_number')];
            $data['$entity.terms']       = ['value' => $this->terms ?: '&nbsp;', 'label' => ctrans('texts.quote_terms')];
            $data['$terms']              = &$data['$entity.terms'];
            $data['$view_link']          = ['value' => '<a href="' .$invitation->getLink() .'">'. ctrans('texts.view_quote').'</a>', 'label' => ctrans('texts.view_quote')];
       }

        if ($this instanceof Credit) {
            $data['$entity_label']       = ['value' => '', 'label' => ctrans('texts.credit')];
            $data['$number']             = ['value' => $this->number ?: '&nbsp;', 'label' => ctrans('texts.credit_number')];
            $data['$entity.terms']       = ['value' => $this->terms ?: '&nbsp;', 'label' => ctrans('texts.credit_terms')];
            $data['$terms']              = &$data['$entity.terms'];
            $data['$view_link']          = ['value' => '<a href="' .$invitation->getLink() .'">'. ctrans('texts.view_credit').'</a>', 'label' => ctrans('texts.view_credit')];
        }

        $data['$entity_number']          = &$data['$number'];

        //$data['$paid_to_date'] = ;
        $data['$invoice.discount']       = ['value' => Number::formatMoney($calc->getTotalDiscount(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.discount')];
        $data['$discount']               = &$data['$invoice.discount'];
        $data['$subtotal']               = ['value' => Number::formatMoney($calc->getSubTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.subtotal')];
        $data['$invoice.subtotal']       = &$data['$subtotal'];
        $data['$invoice.balance_due']    = ['value' => Number::formatMoney($this->balance, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.balance_due')];
        $data['$quote.balance_due']      = &$data['$invoice.balance_due'];
        $data['$balance_due']            = &$data['$invoice.balance_due'];
        $data['$invoice.partial_due']    = ['value' => Number::formatMoney($this->partial, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.partial_due')];
        $data['$total']                  = ['value' => Number::formatMoney($calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.total')];
        $data['$amount']                 = &$data['$total'];
        $data['$quote.total']            = &$data['$total'];
        $data['$invoice.total']          = ['value' => Number::formatMoney($calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.invoice_total')];
        $data['$invoice.amount']         = &$data['$total'];
        $data['$quote.amount']           = ['value' => Number::formatMoney($calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.quote_total')];
        $data['$credit.total']           = ['value' => Number::formatMoney($calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.credit_total')];
        $data['$credit.number']          = ['value' => $this->number ?: '&nbsp;', 'label' => ctrans('texts.credit_number')];
        $data['$credit.amount']          = &$data['$credit.total'];
        $data['$credit.po_number']       = &$data['$invoice.po_number'];
        $data['$credit.date']            = ['value' => $this->date, 'label' => ctrans('texts.credit_date')];
        $data['$balance']                = ['value' => Number::formatMoney($calc->getBalance(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.balance')];
        $data['$credit.balance']         = &$data['$balance'];

        $data['$invoice.balance']        = &$data['$balance'];
        $data['$taxes']                  = ['value' => Number::formatMoney($calc->getItemTotalTaxes(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.taxes')];
        $data['$invoice.taxes']          = &$data['$taxes'];
        
        $data['$invoice.custom1']        = ['value' => $this->custom_value1 ?: '&nbsp;', 'label' => $this->makeCustomField('invoice1')];
        $data['$invoice.custom2']        = ['value' => $this->custom_value2 ?: '&nbsp;', 'label' => $this->makeCustomField('invoice2')];
        $data['$invoice.custom3']        = ['value' => $this->custom_value3 ?: '&nbsp;', 'label' => $this->makeCustomField('invoice3')];
        $data['$invoice.custom4']        = ['value' => $this->custom_value4 ?: '&nbsp;', 'label' => $this->makeCustomField('invoice4')];
        $data['$invoice.public_notes']   = ['value' => $this->public_notes ?: '&nbsp;', 'label' => ctrans('texts.public_notes')];
        $data['$entity.public_notes']    = &$data['$invoice.public_notes'];
        
        // $data['$your_invoice'] = ;
        // $data['$quote'] = ;
        // $data['$your_quote'] = ;
        //
        $data['$quote.date']             = ['value' => $this->date ?: '&nbsp;', 'label' => ctrans('texts.quote_date')];
        $data['$quote.number']           = ['value' => $this->number ?: '&nbsp;', 'label' => ctrans('texts.quote_number')];
        $data['$quote.po_number']        = &$data['$invoice.po_number'];
        $data['$quote.quote_number']     = &$data['$quote.number'];
        $data['$quote_no']               = &$data['$quote.number'];
        $data['$quote.quote_no']         = &$data['$quote.number'];
        $data['$quote.valid_until']      = ['value' => $this->due_date, 'label' => ctrans('texts.valid_until')];
        $data['$credit_amount']          = ['value' => Number::formatMoney($calc->getTotal(), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.credit_amount')];
        $data['$credit_balance']         = ['value' => Number::formatMoney($this->balance, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.credit_balance')];
        ;
        $data['$credit_number']          = &$data['$number'];
        $data['$credit_no']              = &$data['$number'];
        $data['$credit.credit_no']       = &$data['$number'];

        // $data['$invoice_issued_to'] = ;
        // $data['$quote_issued_to'] = ;
        // $data['$rate'] = ;
        // $data['$hours'] = ;
        // $data['$from'] = ;
        // $data['$to'] = ;
        // $data['$invoice_to'] = ;
        // $data['$quote_to'] = ;
        // $data['$details'] = ;
        $data['$invoice_no']                = &$data['$number'];
        $data['$invoice.invoice_no']        = &$data['$number'];
        $data['$client1']                   = ['value' => $this->client->custom_value1 ?: '&nbsp;', 'label' => $this->makeCustomField('client1')];
        $data['$client2']                   = ['value' => $this->client->custom_value2 ?: '&nbsp;', 'label' => $this->makeCustomField('client2')];
        $data['$client3']                   = ['value' => $this->client->custom_value3 ?: '&nbsp;', 'label' => $this->makeCustomField('client3')];
        $data['$client4']                   = ['value' => $this->client->custom_value4 ?: '&nbsp;', 'label' => $this->makeCustomField('client4')];
        $data['$address1']                  = ['value' => $this->client->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$address2']                  = ['value' => $this->client->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$id_number']                 = ['value' => $this->client->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$vat_number']                = ['value' => $this->client->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$website']                   = ['value' => $this->client->present()->website() ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$phone']                     = ['value' => $this->client->present()->phone() ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$country']                   = ['value' => isset($this->client->country->name) ? $this->client->country->name : 'No Country Set', 'label' => ctrans('texts.country')];
        $data['$email']                     = ['value' => isset($contact) ? $contact->email : 'no contact email on record', 'label' => ctrans('texts.email')];
        $data['$client_name']               = ['value' => $this->present()->clientName() ?: '&nbsp;', 'label' => ctrans('texts.client_name')];
        $data['$client.name']               = &$data['$client_name'];
        $data['$client.address1']           = &$data['$address1'];
        $data['$client.address2']           = &$data['$address2'];
        $data['$client_address']            = ['value' => $this->present()->address() ?: '&nbsp;', 'label' => ctrans('texts.address')];
        $data['$client.address']            = &$data['$client_address'];
        $data['$client.id_number']          = &$data['$id_number'];
        $data['$client.vat_number']         = &$data['$vat_number'];
        $data['$client.website']            = &$data['$website'];
        $data['$client.phone']              = &$data['$phone'];
        $data['$city_state_postal']         = ['value' => $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.city_state_postal']  = &$data['$city_state_postal'];
        $data['$postal_city_state']         = ['value' => $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.postal_city_state']  = &$data['$postal_city_state'];
        $data['$client.country']            = &$data['$country'];
        $data['$client.email']              = &$data['$email'];


        $data['$contact.full_name']         = ['value' => $contact->present()->name(), 'label' => ctrans('texts.name')];
        $data['$contact.email']             = ['value' => $contact->email, 'label' => ctrans('texts.email')];
        $data['$contact.phone']             = ['value' => $contact->phone, 'label' => ctrans('texts.phone')];

        $data['$contact.name']                     = ['value' => isset($contact) ? $contact->present()->name() : 'no contact name on record', 'label' => ctrans('texts.contact_name')];
        $data['$contact.custom1']                  = ['value' => isset($contact) ? $contact->custom_value1 : '&nbsp;', 'label' => $this->makeCustomField('contact1')];
        $data['$contact.custom2']                  = ['value' => isset($contact) ? $contact->custom_value2 : '&nbsp;', 'label' => $this->makeCustomField('contact1')];
        $data['$contact.custom3']                  = ['value' => isset($contact) ? $contact->custom_value3 : '&nbsp;', 'label' => $this->makeCustomField('contact1')];
        $data['$contact.custom4']                  = ['value' => isset($contact) ? $contact->custom_value4 : '&nbsp;', 'label' => $this->makeCustomField('contact1')];

        $data['$company.city_state_postal'] = ['value' => $this->company->present()->cityStateZip($settings->city, $settings->state, $settings->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => $this->company->present()->cityStateZip($settings->city, $settings->state, $settings->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.name']              = ['value' => $this->company->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.company_name')];
        $data['$company.address1']          = ['value' => $settings->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$company.address2']          = ['value' => $settings->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$company.city']              = ['value' => $settings->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$company.state']             = ['value' => $settings->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$company.postal_code']       = ['value' => $settings->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        $data['$company.country']           = ['value' => Country::find($settings->country_id)->first()->name ?: '&nbsp;', 'label' => ctrans('texts.country')];
        $data['$company.phone']             = ['value' => $settings->phone ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$company.email']             = ['value' => $settings->email ?: '&nbsp;', 'label' => ctrans('texts.email')];
        $data['$company.vat_number']        = ['value' => $settings->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number']         = ['value' => $settings->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$company.website']           = ['value' => $settings->website ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$company.address']           = ['value' => $this->company->present()->address($settings) ?: '&nbsp;', 'label' => ctrans('texts.address')];
        
        $logo = $this->company->present()->logo($settings);

        $data['$company.logo']                       = ['value' => "<img src='{$logo}' class='h-32' alt='logo'>" ?: '&nbsp;', 'label' => ctrans('texts.logo')];
        $data['$company_logo']                       = &$data['$company.logo'];
        $data['$company1']                           = ['value' => $settings->custom_value1 ?: '&nbsp;', 'label' => $this->makeCustomField('company1')];
        $data['$company2']                           = ['value' => $settings->custom_value2 ?: '&nbsp;', 'label' => $this->makeCustomField('company2')];
        $data['$company3']                           = ['value' => $settings->custom_value3 ?: '&nbsp;', 'label' => $this->makeCustomField('company3')];
        $data['$company4']                           = ['value' => $settings->custom_value4 ?: '&nbsp;', 'label' => $this->makeCustomField('company4')];

        $data['$product.date']                       = ['value' => '', 'label' => ctrans('texts.date')];
        $data['$product.discount']                   = ['value' => '', 'label' => ctrans('texts.discount')];
        $data['$product.product_key']                = ['value' => '', 'label' => ctrans('texts.product_key')];
        $data['$product.notes']                      = ['value' => '', 'label' => ctrans('texts.notes')];
        $data['$product.cost']                       = ['value' => '', 'label' => ctrans('texts.cost')];
        $data['$product.quantity']                   = ['value' => '', 'label' => ctrans('texts.quantity')];
        $data['$product.tax_name1']                  = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax']                        = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name2']                  = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name3']                  = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.line_total']                 = ['value' => '', 'label' => ctrans('texts.line_total')];

        $data['$task.date']                          = ['value' => '', 'label' => ctrans('texts.date')];
        $data['$task.discount']                      = ['value' => '', 'label' => ctrans('texts.discount')];
        $data['$task.product_key']                   = ['value' => '', 'label' => ctrans('texts.product_key')];
        $data['$task.notes']                         = ['value' => '', 'label' => ctrans('texts.notes')];
        $data['$task.cost']                          = ['value' => '', 'label' => ctrans('texts.cost')];
        $data['$task.quantity']                      = ['value' => '', 'label' => ctrans('texts.quantity')];
        $data['$task.tax']                           = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name1']                     = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name2']                     = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name3']                     = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.line_total']                    = ['value' => '', 'label' => ctrans('texts.line_total')];
        //$data['$contact.signature']

        // $data['custom_label1']              = ['value' => '', 'label' => ctrans('texts.')];
        // $data['custom_label2']              = ['value' => '', 'label' => ctrans('texts.')];
        // $data['custom_label3']              = ['value' => '', 'label' => ctrans('texts.')];
        // $data['custom_label4']              = ['value' => '', 'label' => ctrans('texts.')];
        //$data['$blank'] = ;
        //$data['$surcharge'] = ;
        /*
        $data['$tax_invoice'] =
        $data['$tax_quote'] =
        $data['$statement'] = ;
        $data['$statement_date'] = ;
        $data['$your_statement'] = ;
        $data['$statement_issued_to'] = ;
        $data['$statement_to'] = ;
        $data['$credit_note'] = ;
        $data['$credit_date'] = ;
        $data['$credit_issued_to'] = ;
        $data['$credit_to'] = ;
        $data['$your_credit'] = ;
        $data['$phone'] = ;

        $data['$outstanding'] = ;
        $data['$invoice_due_date'] = ;
        $data['$quote_due_date'] = ;
        $data['$service'] = ;
        $data['$product_key'] = ;
        $data['$unit_cost'] = ;
        $data['$custom_value1'] = ;
        $data['$custom_value2'] = ;
        $data['$delivery_note'] = ;
        $data['$date'] = ;
        $data['$method'] = ;
        $data['$payment_date'] = ;
        $data['$reference'] = ;
        $data['$amount'] = ;
        $data['$amount_paid'] =;
        	*/

        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

        return $data;
    }

    /**
     * V2 of building a table header for PDFs
     * @param  array $columns The array (or string of column headers)
     * @return string  injectable HTML string
     */
    public function buildTableHeader($columns) :?string
    {
        $data = $this->makeLabels();

        $table_header = '<tr>';

        foreach ($columns as $key => $column) {
            $table_header .= '<td class="table_header_td_class">' . $column . '_label</td>';
        }
        
        $table_header .= '</tr>';

        $table_header = strtr($table_header, $data);// str_replace(array_keys($data), array_values($data), $table_header);

        return $table_header;
    }

    /**
     * V2 of building a table body for PDFs
     * @param  array $columns The array (or string of column headers)
     * @return string  injectable HTML string
     */
    public function buildTableBody(array $default_columns, $user_columns, string $table_prefix) :?string
    {
        $items = $this->transformLineItems($this->line_items, $table_prefix);

        if (count($items) == 0) {
            return '';
        }

        $data = $this->makeValues();

        $output = '';

        if (strlen($user_columns) > 1) {
            foreach ($items as $key => $item) {
//                $tmp = str_replace(array_keys($data), array_values($data), $user_columns);
//                $tmp = str_replace(array_keys($item), array_values($item), $tmp);
                $tmp = strtr($user_columns, $data);
                $tmp = strtr($tmp, $item);

                $output .= $tmp;
            }
        } else {
            $table_row = '<tr>';

            foreach ($default_columns as $key => $column) {
                $table_row .= '<td class="table_header_td_class">' . $column . '</td>';
            }
            
            $table_row .= '</tr>';

            foreach ($items as $key => $item) {
                // $tmp = str_replace(array_keys($item), array_values($item), $table_row);
                // $tmp = str_replace(array_keys($data), array_values($data), $tmp);
                $tmp = strtr($table_row, $item);
                $tmp = strtr($tmp, $data);

                $output .= $tmp;
            }
        }

        return $output;
    }

    /**
     * Transform the column headers into translated header values
     *
     * @param  array  $columns The column header values
     * @return array          The new column header variables
     */
    private function transformColumnsForHeader(array $columns) :array
    {
        if (count($columns) == 0) {
            return [];
        }

        $pre_columns = $columns;
        $columns = array_intersect($columns, self::$master_columns);

        return str_replace(
            [
                'tax_name1',
                'tax_name2',
                'tax_name3'
            ],
            [
                'tax',
                'tax',
                'tax'
            ],
            $columns
        );
    }

    /**
     *
     * Transform the column headers into invoice variables
     *
     * @param  array  $columns The column header values
     * @return array          The invoice variables
     */
    private function transformColumnsForLineItems(array $columns) :array
    {
        /* Removes any invalid columns the user has entered. */
        $columns = array_intersect($columns, self::$master_columns);

        return str_replace(
            [
                'custom_invoice_label1',
                'custom_invoice_label2',
                'custom_invoice_label3',
                'custom_invoice_label4',
                'tax_name1',
                'tax_name2',
                'tax_name3'
            ],
            [
                'custom_invoice_value1',
                'custom_invoice_value2',
                'custom_invoice_value3',
                'custom_invoice_value4',
                'tax_rate1',
                'tax_rate2',
                'tax_rate3'
            ],
            $columns
        );
    }

    /**
     * Formats the line items for display
     * @param  array  $items The array of invoice items
     * @return array        The formatted array of invoice items
     */
    private function transformLineItems($items, $table_type = '$product') :array
    {
        $data = [];
        
        if (!is_array($items)) {
            $data;
        }

        foreach ($items as $key => $item) {
            if ($table_type == '$product' && $item->type_id != 1) {
                continue;
            }

            if ($table_type == '$task' && $item->type_id != 2) {
                continue;
            }

            $data[$key][$table_type.'.product_key'] = $item->product_key;
            $data[$key][$table_type.'.notes'] = $item->notes;
            $data[$key][$table_type.'.custom_value1'] = $item->custom_value1;
            $data[$key][$table_type.'.custom_value2'] = $item->custom_value2;
            $data[$key][$table_type.'.custom_value3'] = $item->custom_value3;
            $data[$key][$table_type.'.custom_value4'] = $item->custom_value4;
            $data[$key][$table_type.'.quantity'] = $item->quantity;

            $data[$key][$table_type.'.cost'] = Number::formatMoney($item->cost, $this->client);
            $data[$key][$table_type.'.line_total'] = Number::formatMoney($item->line_total, $this->client);

            if (isset($item->discount) && $item->discount > 0) {
                if ($item->is_amount_discount) {
                    $data[$key][$table_type.'.discount'] = Number::formatMoney($item->discount, $this->client);
                } else {
                    $data[$key][$table_type.'.discount'] = $item->discount . '%';
                }
            } else {
                $data[$key][$table_type.'.discount'] = '';
            }

            if (isset($item->tax_rate1) && $item->tax_rate1 > 0) {
                $data[$key][$table_type.'.tax_rate1'] = round($item->tax_rate1, 2) . "%";
                $data[$key][$table_type.'.tax1'] = &$data[$key][$table_type.'.tax_rate1'];
            }
        
            if (isset($item->tax_rate2) && $item->tax_rate2 > 0) {
                $data[$key][$table_type.'.tax_rate2'] = round($item->tax_rate2, 2) . "%";
                $data[$key][$table_type.'.tax2'] = &$data[$key][$table_type.'.tax_rate2'];
            }

            if (isset($item->tax_rate3) && $item->tax_rate3 > 0) {
                $data[$key][$table_type.'.tax_rate3'] = round($item->tax_rate3, 2) . "%";
                $data[$key][$table_type.'.tax3'] = &$data[$key][$table_type.'.tax_rate3'];
            }

            if (isset($item->tax_rate1) && $item->tax_rate1 == 0) {
                $data[$key][$table_type.'.tax_rate1'] = '';
                $data[$key][$table_type.'.tax1'] = &$data[$key][$table_type.'.tax_rate1'];
            }
        
            if (isset($item->tax_rate2) && $item->tax_rate2 == 0) {
                $data[$key][$table_type.'.tax_rate2'] = '';
                $data[$key][$table_type.'.tax2'] = &$data[$key][$table_type.'.tax_rate2'];
            }

            if (isset($item->tax_rate3) && $item->tax_rate3 == 0) {
                $data[$key][$table_type.'.tax_rate3'] = '';
                $data[$key][$table_type.'.tax3'] = &$data[$key][$table_type.'.tax_rate3'];
            }
        }
    

        return $data;
    }

    /**
     * Due to the way we are compiling the blade template we
     * have no ability to iterate, so in the case
     * of line taxes where there are multiple rows,
     * we use this function to format a section of rows
     *
     * @return string a collection of <tr> rows with line item
     * aggregate data
     */
    private function makeLineTaxes() :string
    {
        $tax_map = $this->calc()->getTaxMap();
        
        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<tr class="line_taxes">';
            $data .= '<td>'. $tax['name'] .'</td>';
            $data .= '<td>'. Number::formatMoney($tax['total'], $this->client) .'</td></tr>';
        }

        return $data;
    }

    /**
     * @return string a collectino of <tr> with
     * itemised total tax data
     */
    
    private function makeTotalTaxes() :string
    {
        $data = '';

        if (!$this->calc()->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->calc()->getTotalTaxMap() as $tax) {
            $data .= '<tr class="total_taxes">';
            $data .= '<td>'. $tax['name'] .'</td>';
            $data .= '<td>'. Number::formatMoney($tax['total'], $this->client) .'</td></tr>';
        }

        return $data;
    }

    private function totalTaxLabels() :string
    {
        $data = '';

        if (!$this->calc()->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->calc()->getTotalTaxMap() as $tax) {
            $data .= '<span>'. $tax['name'] .'</span>';
        }

        return $data;
    }

    private function totalTaxValues() :string
    {
        $data = '';

        if (!$this->calc()->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->calc()->getTotalTaxMap() as $tax) {
            $data .= '<span>'. Number::formatMoney($tax['total'], $this->client) .'</span>';
        }

        return $data;
    }

    private function lineTaxLabels() :string
    {
        $tax_map = $this->calc()->getTaxMap();
        
        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'. $tax['name'] .'</span>';
        }
        
        return $data;
    }

    private function lineTaxValues() :string
    {
        $tax_map = $this->calc()->getTaxMap();
        
        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'. Number::formatMoney($tax['total'], $this->client) .'</span>';
        }
        
        return $data;
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
     * of Repeating headers and footers on the PDF
     * @return string The css string
     */
    public function generateCustomCSS() :string
    {
        $settings = $this->client->getMergedSettings();

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

        if ($settings->all_pages_header && $settings->all_pages_footer) {
            $css .= $header_and_footer;
        } elseif ($settings->all_pages_header && !$settings->all_pages_footer) {
            $css .= $header;
        } elseif (!$settings->all_pages_header && $settings->all_pages_footer) {
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

        $css .= 'font-size:' . $settings->font_size . 'px;';
//        $css .= 'font-size:14px;';

        $css .= '}';

        return $css;
    }
}
