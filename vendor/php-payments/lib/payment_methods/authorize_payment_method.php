<?php
class Authorize_Payment_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "This method ensures that funds are available for a particular transaction and returns an identifier that can later be used to complete the transaction.  This method does not charge a user immediately.";

	public function __construct()
	{
		$this->_params = array(
			'ip_address'		=>	'138.29.23.29',	//IP address of purchaser
			'cc_type'			=>	'Visa',	//Visa, MasterCard, Discover, Amex
			'cc_number'			=>	'4111111111111111', //Credit card number
			'cc_exp'			=>	'022012', //Must be formatted MMYYYY
			'cc_code'			=>	'203', //3 or 4 digit cvv code
			'email'				=>	'calvinsemail@gmail.com', //email associated with account being billed
			'first_name'		=>	'Calvin', //first name of the purchaser
			'last_name'			=>	'Froedge', //last name of the purchaser
			'business_name'		=>	'Mango Reservations', //name of business
			'street'			=>	'Some Street', //street address of the purchaser
			'street2'			=>	'', //street address 2 of purchaser
			'city'				=>	'Honolulu', //city of the purchaser
			'state'				=>	'HI', //state of the purchaser
			'country'		=>	'US', // country of the purchaser
			'postal_code'				=>	'94105', //zip code of the purchaser
			'amt'				=>	'50.00', //purchase amount
			'phone'	=>	'', //phone num of customer shipped to
			'fax'				=>	'(801) 754 4466',
			'identifier' => '23432', //Merchant provided identifier for the transaction
			'currency_code'		=>	'USD', //currency code to use for the transaction.
			'item_amt'			=>	'40.00', //Amount for just the item being purchased.
			'insurance_amt'		=>	'2.00', //Amount for just insurance.
			'shipping_disc_amt'	=>	'5.00', //Amount for just shipping.
			'handling_amt'		=>	'2.00', //Amount for just handling.
			'tax_amt'			=>	'1.00', //Amount for just tax.
			'desc'				=>	'Some cool thing Calvin wants, probably a good programming book.', //Description for the transaction
			'custom'			=>	'Some custom info', //Free form text field
			'inv_num'			=>	'234323', //Invoice number
			'notify_url'		=>	'http://notify.me/url',	//Your URL for receiving Instant Payment Notification (IPN) about this transaction. If you do not specify this value in the request, the notification URL from your Merchant Profile is used, if one exists.
			'ship_to_first_name'=>	'Some',
			'ship_to_last_name'	=>	'Dude',
			'ship_to_street'	=>	'100 Somewhere Street',			
			'ship_to_city'		=>	'Somwheresville',
			'ship_to_state'		=>	'TN',
			'ship_to_postal_code'=>	'38501',
			'ship_to_country'	=>	'US',	
			'ship_to_company'	=>	'Calvin\'s Company',
			'shipping_amt'		=>	'5.00',
			'duty_amt'			=>	'0.00',
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