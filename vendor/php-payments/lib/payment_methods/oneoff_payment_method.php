<?php

class Oneoff_Payment_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Performs authorize and capture actions simultaneously.  User is charged immediately.";

	public function __construct()
	{
		$this->_params = array(
			'ip_address'		=>	'142.392.29.21',	//IP address of purchaser
			'cc_type'			=>	'Visa',	//Visa, MasterCard, Discover, Amex
			'cc_number'			=>	'4111111111111111', //Credit card number
			'cc_exp'			=>	'022013', //Must be formatted MMYYYY
			'cc_code'			=>	'413', //3 or 4 digit cvv code
			'email'				=>	'calvinsemail@gmail.com', //email associated with account being billed
			'first_name'		=>	'Calvin', //first name of the purchaser
			'last_name'			=>	'Froedge', //last name of the purchaser
			'business_name'		=>	'The Business Name', //name of business
			'street'			=>	'251 Somewhere Street', //street address of the purchaser
			'street2'			=>	'Apt B', //street address 2 of purchaser
			'city'				=>	'Somewherton', //city of the purchaser
			'state'				=>	'KY', //state of the purchaser
			'country'			=>	'US', // country of the purchaser
			'postal_code'		=>	'42105', //zip code of the purchaser
			'amt'				=>	'25.00', //purchase amount
			'phone'				=>	'(801) 754 4466', //phone num of customer shipped to
			'fax'				=>	'(801) 754 4466',
			'identifier' 		=>  'YOUR-IDENTIFIER', //Merchant provided identifier for the transaction
			'currency_code'		=>	'USD', //currency code to use for the transaction.
			'item_amt'			=>	'25.00', //Amount for just the item being purchased.
			'insurance_amt'		=>	'0.00', //Amount for just insurance.
			'shipping_disc_amt'	=>	'0.00', 
			'handling_amt'		=>	'0.00', //Amount for just handling.
			'tax_amt'			=>	'0.00', //Amount for just tax.
			'desc'				=>	'The transaction description', //Description for the transaction
			'custom'			=>	'Anything you want to put here', //Free form text field
			'inv_num'			=>	'YOUR-INV-NUMBER', //Invoice number
			'po_num'			=>	'YOUR-PO-NUMBER',
			'notify_url'		=>	'http://notify.me/url',	//Your URL for receiving Instant Payment Notification (IPN) about this transaction. If you do not specify this value in the request, the notification URL from your Merchant Profile is used, if one exists.
			'ship_to_first_name'=>	'Some',
			'ship_to_last_name' =>	'Dude',
			'ship_to_street'	=>	'311 North Washington Avenue',
			'ship_to_city'		=>	'Cookeville',
			'ship_to_state'		=>	'TN',
			'ship_to_postal_code'=>	'38501',
			'ship_to_country'	=>	'US',	
			'ship_to_company'	=>	'Some Company',
			'shipping_amt'		=>	'0.00', //Amount for just shipping.
		);
	}

	public function get_params()
	{
		return $this->_params;
	}

	public function get_description()
	{
		return $this->_descrip();
	}
}