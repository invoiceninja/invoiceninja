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

use Illuminate\Support\Facades\Log;

/**
 * Class MakesInvoiceLabels
 * @package App\Utils\Traits
 */
trait MakesInvoiceLabels
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

	public function invoice_number_label()
	{
		return ctrans('texts.invoice_number');
	}


	/**
	 * 	Returns a single translated string which 
	 * 	has been appended with _label
	 *
	 *  Used for design templates where we need both
	 *  the value and its _label
	 *
	 *  ie: $invoice_number and $invoice_number_label
	 * 
	 * 
	 * @param  string $label The label to translate
	 * 
	 * @return string       The translated label
	 */
	public function makeLabel(string $label) :string
	{
	
		if (in_array(str_replace("_label", "", $label), self::$labels))
		{
			return ctrans('texts.' . $label);
		}
		else
			return 'label does not exist';
		

    }	

    /**
     * Iterates and translates all labels
     *
     * @return array returns an array of keyed labels (appended with _label)
     */
    public function makeLabels() : array
    {
    	$data = [];

    	foreach(self::$labels as $label)
    		$data[][$label . '_label'] = ctrans('texts'.$label);

    	return $data;
    }