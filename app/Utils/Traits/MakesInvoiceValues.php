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

    /**
     * Master list of invoice labels
     * @var array
     */
    private static $labels = [
        'invoice',
        'date',
        'due_date',
        'invoice_number',
        'po_number',
        'discount',
        'taxes',
        'tax',
        'item',
        'description',
        'unit_cost',
        'quantity',
        'line_total',
        'subtotal',
        'paid_to_date',
        'balance_due',
        'partial_due',
        'terms',
        'your_invoice',
        'quote',
        'your_quote',
        'quote_date',
        'quote_number',
        'total',
        'invoice_issued_to',
        'quote_issued_to',
        'rate',
        'hours',
        'balance',
        'from',
        'to',
        'invoice_to',
        'quote_to',
        'details',
        'invoice_no',
        'quote_no',
        'valid_until',
        'client_name',
        'address1',
        'address2',
        'id_number',
        'vat_number',
        'city_state_postal',
        'postal_city_state',
        'country',
        'email',
        'contact_name',
        'company_name',
        'website',
        'phone',
        'blank',
        'surcharge',
        'tax_invoice',
        'tax_quote',
        'statement',
        'statement_date',
        'your_statement',
        'statement_issued_to',
        'statement_to',
        'credit_note',
        'credit_date',
        'credit_number',
        'credit_issued_to',
        'credit_to',
        'your_credit',
        'phone',
        'invoice_total',
        'outstanding',
        'invoice_due_date',
        'quote_due_date',
        'service',
        'product_key',
        'unit_cost',
        // 'custom_value1',
        // 'custom_value2',
        // 'custom_value3',
        // 'custom_value4',
        'delivery_note',
        'date',
        'method',
        'payment_date',
        'reference',
        'amount',
        'amount_paid',
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

    /**
     * Iterates and translates all labels
     *
     * @return array returns an array of
     * keyed labels (appended with _label)
     */
    public function makeLabels() :array
    {
        $custom_fields = $this->company->custom_fields;

        //todo we might want to translate like this
        //trans('texts.labe', [], null, $this->client->locale());
        $data = [];

        foreach (self::$labels as $label) {
            $data['$'.$label . '_label'] = ctrans('texts.'.$label);
        }

        if($custom_fields)
        {

            foreach($custom_fields as $key => $value)
            {

                if(strpos($value, '|') !== false)
                {
                    $value = explode("|", $value);
                    $value = $value[0];
                }

                $data['$'.$key.'_label'] = $value;
            }

        }
         

        /*
        Don't forget pipe | strings for dropdowns needs to be filtered
         */

        /*
        invoice1
        invoice2
        invoice3
        invoice4
        surcharge1
        surcharge2
        surcharge3
        surcharge4
        client1
        client2
        client3
        client4
        contact1
        contact2
        contact3
        contact4
         */
        
        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

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
        if (!$this->client->currency() || !$this->client) {
            throw new \Exception(debug_backtrace()[1]['function'], 1);
            exit;
        }
        
        $settings = $this->client->getMergedSettings();

        $data = [];

        $data['$total_tax_labels'] = $this->totalTaxLabels();
        $data['$total_tax_values'] = $this->totalTaxValues();
        $data['$line_tax_labels'] = $this->lineTaxLabels();
        $data['$line_tax_values'] = $this->lineTaxValues();

        $data['$date'] = $this->date ?: '&nbsp;';
        $data['$invoice.date'] = &$data['$date'];
        $data['$due_date'] = $this->due_date ?: '&nbsp;';
        $data['$invoice.due_date'] = &$data['$due_date'];
        $data['$number'] = $this->number ?: '&nbsp;';
        $data['$invoice.number'] = &$data['$number'];
        $data['$invoice_number'] = &$data['$number'];
        $data['$po_number'] = $this->po_number ?: '&nbsp;';
        $data['$invoice.po_number'] = &$data['$po_number'];
        $data['$line_taxes'] = $this->makeLineTaxes() ?: '&nbsp;';
        $data['$invoice.line_taxes'] = &$data['$line_taxes'];
        $data['$total_taxes'] = $this->makeTotalTaxes() ?: '&nbsp;';
        $data['$invoice.total_taxes'] = &$data['$total_taxes'];
        // $data['$tax'] = ;
        // $data['$item'] = ;
        // $data['$description'] = ;
        // $data['$unit_cost'] = ;
        // $data['$quantity'] = ;
        // $data['$line_total'] = ;
        //        $data['$paid_to_date'] = ;
        $data['$discount'] = Number::formatMoney($this->calc()->getTotalDiscount(), $this->client) ?: '&nbsp;';
        $data['$invoice.discount'] = &$data['$discount'];
        $data['$subtotal'] = Number::formatMoney($this->calc()->getSubTotal(), $this->client) ?: '&nbsp;';
        $data['$invoice.subtotal'] = &$data['$subtotal'];
        $data['$balance_due'] = Number::formatMoney($this->balance, $this->client) ?: '&nbsp;';
        $data['$invoice.balance_due'] = &$data['$balance_due'];
        $data['$partial_due'] = Number::formatMoney($this->partial, $this->client) ?: '&nbsp;';
        $data['$invoice.partial_due'] = &$data['$partial_due'];
        $data['$total'] = Number::formatMoney($this->calc()->getTotal(), $this->client) ?: '&nbsp;';
        $data['$invoice.total'] = &$data['$total'];
        $data['$amount'] = &$data['$total'];
        $data['$invoice_total'] =  &$data['$total'];
        $data['$invoice.amount'] = &$data['$total'];

        $data['$balance'] = Number::formatMoney($this->calc()->getBalance(), $this->client) ?: '&nbsp;';
        $data['$invoice.balance'] = &$data['$balance'];
        $data['$taxes'] = Number::formatMoney($this->calc()->getItemTotalTaxes(), $this->client) ?: '&nbsp;';
        $data['$invoice.taxes'] = &$data['$taxes'];
        $data['$terms'] = $this->terms ?: '&nbsp;';
        $data['$invoice.terms'] = &$data['$terms'];
        $data['$invoice1'] = $this->custom_value1 ?: '&nbsp;';
        $data['$invoice2'] = $this->custom_value2 ?: '&nbsp;';
        $data['$invoice3'] = $this->custom_value3 ?: '&nbsp;';
        $data['$invoice4'] = $this->custom_value4 ?: '&nbsp;';
        $data['$invoice.public_notes'] = $this->public_notes ?: '&nbsp;';
        // $data['$your_invoice'] = ;
        // $data['$quote'] = ;
        // $data['$your_quote'] = ;
        // $data['$quote_date'] = ;
        // $data['$quote_number'] = ;
        // $data['$invoice_issued_to'] = ;
        // $data['$quote_issued_to'] = ;
        // $data['$rate'] = ;
        // $data['$hours'] = ;
        // $data['$from'] = ;
        // $data['$to'] = ;
        // $data['$invoice_to'] = ;
        // $data['$quote_to'] = ;
        // $data['$details'] = ;
        $data['$invoice_no'] = $this->number ?: '&nbsp;';
        $data['$invoice.invoice_no'] = &$data['$invoice_no'];
        // $data['$quote_no'] = ;
        // $data['$valid_until'] = ;
        $data['$client1'] = $this->client->custom_value1 ?: '&nbsp;';
        $data['$client2'] = $this->client->custom_value2 ?: '&nbsp;';
        $data['$client3'] = $this->client->custom_value3 ?: '&nbsp;';
        $data['$client4'] = $this->client->custom_value4 ?: '&nbsp;';
        $data['$client_name'] = $this->present()->clientName() ?: '&nbsp;';
        $data['$client.name'] = &$data['$client_name'];
        $data['$address1'] = $this->client->address1 ?: '&nbsp;';
        $data['$address2'] = $this->client->address2 ?: '&nbsp;';
        $data['$client.address2'] = &$data['$address2'];
        $data['$client.address1'] = &$data['$address1'];
        $data['$client.address'] = &$data['$client_address'];
        $data['$client_address'] = $this->present()->address() ?: '&nbsp;';
        $data['$id_number'] = $this->client->id_number ?: '&nbsp;';
        $data['$client.id_number'] = &$data['$id_number'];
        $data['$vat_number'] = $this->client->vat_number ?: '&nbsp;';
        $data['$client.vat_number'] = &$data['$vat_number'];
        $data['$website'] = $this->client->present()->website() ?: '&nbsp;';
        $data['$client.website'] = &$data['$website'];
        $data['$phone'] = $this->client->present()->phone() ?: '&nbsp;';
        $data['$client.phone'] = &$data['$phone'];
        $data['$city_state_postal'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, false) ?: '&nbsp;';
        $data['$client.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, true) ?: '&nbsp;';
        $data['$client.postal_city_state'] = &$data['$postal_city_state'];
        $data['$country'] = isset($this->client->country->name) ? $this->client->country->name : 'No Country Set';
        $data['$client.country'] = &$data['$country'];
        $data['$email'] = isset($this->client->primary_contact()->first()->email) ? $this->client->primary_contact()->first()->email : 'no contact email on record';
        $data['$client.email'] = &$data['$email'];

        if(!$contact)
            $contact = $this->client->primary_contact()->first();

        $data['$contact_name'] = isset($contact) ? $contact->present()->name() : 'no contact name on record';
        $data['$contact.name'] = &$data['$contact_name'];
        $data['$contact1'] = isset($contact) ? $contact->custom_value1 : '&nbsp;';
        $data['$contact2'] = isset($contact) ? $contact->custom_value2 : '&nbsp;';
        $data['$contact3'] = isset($contact) ? $contact->custom_value3 : '&nbsp;';
        $data['$contact4'] = isset($contact) ? $contact->custom_value4 : '&nbsp;';

        $data['$company.city_state_postal'] = $this->company->present()->cityStateZip($settings->city, $settings->state, $settings->postal_code, false) ?: '&nbsp;';
        $data['$company.postal_city_state'] = $this->company->present()->cityStateZip($settings->city, $settings->state, $settings->postal_code, true) ?: '&nbsp;';
        $data['$company.name'] = $this->company->present()->name() ?: '&nbsp;';
        $data['$company.company_name'] = &$data['$company.name'];
        $data['$company.address1'] = $settings->address1 ?: '&nbsp;';
        $data['$company.address2'] = $settings->address2 ?: '&nbsp;';
        $data['$company.city'] = $settings->city ?: '&nbsp;';
        $data['$company.state'] = $settings->state ?: '&nbsp;';
        $data['$company.postal_code'] = $settings->postal_code ?: '&nbsp;';
        $data['$company.country'] = Country::find($settings->country_id)->first()->name ?: '&nbsp;';
        $data['$company.phone'] = $settings->phone ?: '&nbsp;';
        $data['$company.email'] = $settings->email ?: '&nbsp;';
        $data['$company.vat_number'] = $settings->vat_number ?: '&nbsp;';
        $data['$company.id_number'] = $settings->id_number ?: '&nbsp;';
        $data['$company.website'] = $settings->website ?: '&nbsp;';
        $data['$company.address'] = $this->company->present()->address($settings) ?: '&nbsp;';
        
        $logo = $this->company->present()->logo($settings);

        $data['$company.logo'] = "<img src='{$logo}' class='w-48' alt='logo'>" ?: '&nbsp;';
        $data['$company_logo'] = &$data['$company.logo'];
        $data['$company1'] = $settings->custom_value1 ?: '&nbsp;';
        $data['$company2'] = $settings->custom_value2 ?: '&nbsp;';
        $data['$company3'] = $settings->custom_value3 ?: '&nbsp;';
        $data['$company4'] = $settings->custom_value4 ?: '&nbsp;';
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
        $data['$credit_number'] = ;
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
        //     \Log::error('woop');
        //\Log::error(print_r($data,1));

        return $data;
    }

    /**
     * Returns a formatted HTML table of invoice line items
     *
     * @param array $columns The columns to be displayed
     *
     * @return string[HTML string
     */
    public function table(array $columns) :?string
    {
        $data = '<table class="table table-striped items">';
        $data .= '<thead><tr class="heading">';

        $column_headers = $this->transformColumnsForHeader($columns);

        foreach ($column_headers as $column) {
            $data .= '<td>' . ctrans('texts.'.$column.'') . '</td>';
        }

        $data .= '</tr></thead>';

        $columns = $this->transformColumnsForLineItems($columns);

        $items = $this->transformLineItems($this->line_items);

        foreach ($items as $item) {
            $data .= '<tr class="item">';

            foreach ($columns as $column) {
                $data .= '<td>'. $item->{$column} . '</td>';
            }
            $data .= '</tr>';
        }

        $data .= '</table>';

        return $data;
    }


    public function table_header(array $columns, array $css) :?string
    {

        /* Table Header */
        //$table_header = '<thead><tr class="'.$css['table_header_thead_class'].'">';

        $table_header = '';
        
        $column_headers = $this->transformColumnsForHeader($columns);

        foreach ($column_headers as $column) 
            $table_header .= '<td class="'.$css['table_header_td_class'].'">' . ctrans('texts.'.$column.'') . '</td>';
        
        //$table_header .= '</tr></thead>';

        return $table_header;

    }

    public function table_body(array $columns, array $css) :?string
    {
        $table_body = '';

        /* Table Body */
        $columns = $this->transformColumnsForLineItems($columns);

        $items = $this->transformLineItems($this->line_items);

        foreach ($items as $item) {

            $table_body .= '<tr class="">';

            foreach ($columns as $column) {
                $table_body .= '<td class="'.$css['table_body_td_class'].'">'. $item->{$column} . '</td>';
            }

            $table_body .= '</tr>';
        }

        return $table_body;
    }

    /**
     * Transform the column headers into translated header values
     *
     * @param  array  $columns The column header values
     * @return array          The new column header variables
     */
    private function transformColumnsForHeader(array $columns) :array
    {
        $pre_columns = $columns;
        $columns = array_intersect($columns, self::$master_columns);

        return str_replace(
            [
                'tax_name1',
                'tax_name2'
            ],
            [
                'tax',
                'tax',
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
                'tax_name2'
            ],
            [
                'custom_invoice_value1',
                'custom_invoice_value2',
                'custom_invoice_value3',
                'custom_invoice_value4',
                'tax_rate1',
                'tax_rate2'
            ],
            $columns
        );
    }

    /**
     * Formats the line items for display
     * @param  array  $items The array of invoice items
     * @return array        The formatted array of invoice items
     */
    private function transformLineItems(array $items) :array
    {
        foreach ($items as $item) {
            $item->cost = Number::formatMoney($item->cost, $this->client);
            $item->line_total = Number::formatMoney($item->line_total, $this->client);

            if (isset($item->discount) && $item->discount > 0) {
                if ($item->is_amount_discount) {
                    $item->discount = Number::formatMoney($item->discount, $this->client);
                } else {
                    $item->discount = $item->discount . '%';
                }
            }
            else
                $item->discount = '';

            if(isset($item->tax_rate1) && $item->tax_rate1 > 0)
                $item->tax_rate1 = $item->tax_rate1."%";
        
            if(isset($item->tax_rate2) && $item->tax_rate2 > 0)
                $item->tax_rate2 = $item->tax_rate2."%";

            if(isset($item->tax_rate2) && $item->tax_rate2 > 0)
                $item->tax_rate2 = $item->tax_rate2."%";

            if(isset($item->tax_rate1) && $item->tax_rate1 == 0)
                $item->tax_rate1 = '';
        
            if(isset($item->tax_rate2) && $item->tax_rate2 == 0)
                $item->tax_rate2 = '';

            if(isset($item->tax_rate2) && $item->tax_rate2 == 0)
                $item->tax_rate2 = '';
        }
    

        return $items;
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

        foreach ($tax_map as $tax) 
            $data .= '<span>'. $tax['name'] .'</span>';
        
        return $data;
    }

    private function lineTaxValues() :string
    {
        $tax_map = $this->calc()->getTaxMap();
        
        $data = '';

        foreach ($tax_map as $tax) 
            $data .= '<span>'. Number::formatMoney($tax['total'], $this->client) .'</span>';
        
        return $data;
    }
}
