<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\Utils\Number;
use Illuminate\Support\Facades\Log;

/**
 * Class MakesInvoiceValues
 * @package App\Utils\Traits
 */
trait MakesInvoiceValues
{


    private static $master_columns = [
        'date',
        'discount',
        'product_key',
        'notes',
        'cost',
        'quantity',
        'tax_name1',
        'tax_name2',
        'line_total',
        'custom_label1',
        'custom_label2',
        'custom_label3',
        'custom_label4',
    ];

	private static $labels = [
        'invoice_date',
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
        'work_phone',
        'invoice_total',
        'outstanding',
        'invoice_due_date',
        'quote_due_date',
        'service',
        'product_key',
        'unit_cost',
        'custom_value1',
        'custom_value2',
        'delivery_note',
        'date',
        'method',
        'payment_date',
        'reference',
        'amount',
        'amount_paid',
    ];

    public function makeInvoiceTemplateKeys()
    {
        $data = [];

        foreach(self::$labels as $label)
            $data[] = '$'.$label.'_label';

        foreach(self::$labels as $label)
            $data[] = '$'.$label;

        return $data;
    }

	/**
     * Iterates and translates all labels
     *
     * @return array returns an array of keyed labels (appended with _label)
     */
    public function makeLabels() :array
    {
    	$data = [];

    	foreach(self::$labels as $label)
    		$data['$'.$label . '_label'] = ctrans('texts.'.$label);

    	return $data;
    }  

	/**
     * Transforms all placeholders to invoice values
     * 
     * @return array returns an array of keyed labels (appended with _label)
     */
    public function makeValues() :array
    {
        $data = [];

            $data['$invoice_date'] = $this->invoice_date;
            $data['$due_date'] = $this->due_date;
            $data['$invoice_number'] = $this->invoice_number;
            $data['$po_number'] = $this->po_number;
            $data['$discount'] = $this->calc()->getTotalDiscount();
            $data['$taxes'] = $this->calc()->getTotalTaxes();
            $data['$line_taxes'] = $this->calc()->getTaxMap();
            // $data['$tax'] = ;
            // $data['$item'] = ;
            // $data['$description'] = ;
            // $data['$unit_cost'] = ;
            // $data['$quantity'] = ;
            // $data['$line_total'] = ;
    //        $data['$paid_to_date'] = ;
            $data['$subtotal'] = Number::formatMoney($this->calc()->getSubTotal(), $this->client->currency(), $this->client->country, $this->client->settings);
            $data['$balance_due'] = Number::formatMoney($this->balance, $this->client->currency(), $this->client->country, $this->client->settings);
            $data['$partial_due'] = Number::formatMoney($this->partial, $this->client->currency(), $this->client->country, $this->client->settings);
            $data['$total'] = Number::formatMoney($this->calc()->getTotal(), $this->client->currency(), $this->client->country, $this->client->settings);
            $data['$balance'] = Number::formatMoney($this->calc()->getBalance(), $this->client->currency(), $this->client->country, $this->client->settings);
            $data['$terms'] = $this->terms;
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
            $data['$invoice_no'] = $this->invoice_number;
            // $data['$quote_no'] = ;
            // $data['$valid_until'] = ;
            $data['$client_name'] = $this->present()->clientName();
            $data['$address1'] = $this->client->address1;
            $data['$address2'] = $this->client->address2;
            $data['$id_number'] = $this->client->id_number;
            $data['$vat_number'] = $this->client->vat_number;
            $data['$city_state_postal'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, FALSE);
            $data['$postal_city_state'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, TRUE);
            $data['$country'] = $this->client->country->name;
            $data['$email'] = isset($this->client->primary_contact()->first()->email) ?: 'no primary contact set';
            $data['$contact_name'] = $this->client->present()->primary_contact_name();
            $data['$company_name'] = $this->company->present()->name();
            $data['$company_address'] = $this->company->present()->address();
            $data['$company_logo'] = $this->company->present()->logo();
            $data['$website'] = $this->client->present()->website();
            $data['$phone'] = $this->client->present()->phone();
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
            $data['$work_phone'] = ;
            $data['$invoice_total'] = ;
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

    	$data = '<table class="table table-hover table-striped">';

    	$data .= '<thead><tr class="heading">';

        $column_headers = $this->transformColumnsForHeader($columns);

    		foreach($column_headers as $column)
    			$data .= '<td>' . ctrans('texts.'.$column.'') . '</td>';

    		$data .= '</tr></thead>';

    		$columns = $this->transformColumnsForLineItems($columns);

    		$items = $this->transformLineItems($this->line_items);

    		foreach($items as $item)
    		{	

    	    	$data .= '<tr class="item">';

        			foreach($columns as $column)
        			{

                	   $data .= '<td>'. $item->{$column} . '</td>';

                    }
    	    	$data .= '</tr>';
	    	
	    	}

    	$data .= '</table>';

        return $data;
    }


    /**
     * 
     * Transform the column headers into translated header values
     * 
     * @param  array  $columns The column header values
     * @return array          The new column header variables
     */
    private function transformColumnsForHeader(array $columns) :array
    {
        $pre_columns = $columns;
        $columns = array_intersect($columns, self::$master_columns);

        return str_replace([
                'tax_name1',
                'tax_name2'
            ], 
            [
                'tax',
                'tax',
            ], 
            $columns);
    
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

    	return str_replace([
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
			$columns);
    
    }

    /**
     * Formats the line items for display
     * @param  array  $items The array of invoice items
     * @return array        The formatted array of invoice items
     */
    private function transformLineItems(array $items) :array
    {

        foreach($items as $item)
        {

            $item->cost = Number::formatMoney($item->cost, $this->client->currency(), $this->client->country, $this->client->getMergedSettings());
            $item->line_total = Number::formatMoney($item->line_total, $this->client->currency(), $this->client->country, $this->client->getMergedSettings());

            if(isset($item->discount) && $item->discount > 0)
            {

                if($item->is_amount_discount)
                    $item->discount = Number::formatMoney($item->discount, $this->client->currency(), $this->client->country, $this->client->getMergedSettings());
                else
                    $item->discount = $item->discount . '%';
            }

        }
    

        return $items;    
    }
}