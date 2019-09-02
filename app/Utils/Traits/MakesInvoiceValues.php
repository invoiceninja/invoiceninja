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
	private static $labels = [
            'invoice',
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

	/**
     * Iterates and translates all labels
     *
     * @return array returns an array of keyed labels (appended with _label)
     */
    public function makeLabels() :array
    {
    	$data = [];

    	foreach(self::$labels as $label)
    		$data[][$label . '_label'] = ctrans('texts'.$label);

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

            $data['invoice'] = $this;
            $data['invoice_date'] = $this->invoice_date;
            $data['due_date'] = $this->due_date;
            $data['invoice_number'] = $this->invoice_number;
            $data['po_number'] = $this->po_number;
            // $data['discount'] = ;
            // $data['taxes'] = ;
            // $data['tax'] = ;
            // $data['item'] = ;
            // $data['description'] = ;
            // $data['unit_cost'] = ;
            // $data['quantity'] = ;
            // $data['line_total'] = ;
            // $data['subtotal'] = ;
    //        $data['paid_to_date'] = ;
            $data['balance_due'] = Number::formatMoney($this->balance, $this->client->currency(), $this->client->country, $this->client->settings);
            $data['partial_due'] = Number::formatMoney($this->partial, $this->client->currency(), $this->client->country, $this->client->settings);
            $data['terms'] = $this->terms;
            // $data['your_invoice'] = ;
            // $data['quote'] = ;
            // $data['your_quote'] = ;
            // $data['quote_date'] = ;
            // $data['quote_number'] = ;
            $data['total'] = Number::formatMoney($this->amount, $this->client->currency(), $this->client->country, $this->client->settings);
            // $data['invoice_issued_to'] = ;
            // $data['quote_issued_to'] = ;
            // $data['rate'] = ;
            // $data['hours'] = ;
            // $data['balance'] = ;
            // $data['from'] = ;
            // $data['to'] = ;
            // $data['invoice_to'] = ;
            // $data['quote_to'] = ;
            // $data['details'] = ;
            $data['invoice_no'] = $this->invoice_number;
            // $data['quote_no'] = ;
            // $data['valid_until'] = ;
            $data['client_name'] = $this->present()->clientName();
            $data['address1'] = $this->client->address1;
            $data['address2'] = $this->client->address2;
            $data['id_number'] = $this->client->id_number;
            $data['vat_number'] = $this->client->vat_number;
            $data['city_state_postal'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, FALSE);
            $data['postal_city_state'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, TRUE);
            $data['country'] = $this->client->country->name;
            $data['email'] = isset($this->client->primary_contact()->first()->email) ?: 'no primary contact set';
            $data['contact_name'] = $this->client->present()->primary_contact_name();
            $data['company_name'] = $this->company->name;
            $data['website'] = $this->client->website;
            $data['phone'] = $this->client->primary_contact->first()->phone;
            //$data['blank'] = ;
            //$data['surcharge'] = ;
            /*
            $data['tax_invoice'] = 
            $data['tax_quote'] = 
            $data['statement'] = ;
            $data['statement_date'] = ;
            $data['your_statement'] = ;
            $data['statement_issued_to'] = ;
            $data['statement_to'] = ;
            $data['credit_note'] = ;
            $data['credit_date'] = ;
            $data['credit_number'] = ;
            $data['credit_issued_to'] = ;
            $data['credit_to'] = ;
            $data['your_credit'] = ;
            $data['work_phone'] = ;
            $data['invoice_total'] = ;
            $data['outstanding'] = ;
            $data['invoice_due_date'] = ;
            $data['quote_due_date'] = ;
            $data['service'] = ;
            $data['product_key'] = ;
            $data['unit_cost'] = ;
            $data['custom_value1'] = ;
            $data['custom_value2'] = ;
            $data['delivery_note'] = ;
            $data['date'] = ;
            $data['method'] = ;
            $data['payment_date'] = ;
            $data['reference'] = ;
            $data['amount'] = ;
            $data['amount_paid'] =;
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
    public function table(array $columns) :string
    {
    	$data = '<table class="table table-hover table-striped">';

    	$data .= '<thead><tr class="heading">';

    		foreach($columns as $column)
    			$data .= '<td>' . ctrans('texts.column') . '</td>';

    	$data .= '</tr></thead>';

    		foreach($this->line_items as $item)
    		{	
	    	$data .= '<tr class="item">';

    			foreach($columns as $column)
    				$data .= '<td>{$item->column}</td>';

	    	$data .= '</tr>';
	    	}
    	$data .= '</table>';
    }

}