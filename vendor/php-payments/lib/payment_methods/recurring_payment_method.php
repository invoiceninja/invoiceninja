<?php

class Recurring_Payment_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "A transaction which repeats on a defined interval.  Some gateways allow a trial interval as well.";

	public function __construct()
	{
		$this->_params = array(
			'profile_start_date'		=>	'2012-05-31', //Required.  The subscription start date.
			'profile_reference'			=>	'REF-NUM', //A reference from your own subscription / invoicing system for the subscriber.
			'desc'						=>	'This is my description',	//Required.  A description for the recurring bill.
			'max_failed_payments'		=>	'3', //Maximum # of failed payments before subscription is cancelled
			'auto_bill_amt'				=>	'5.00', // ?
			'billing_period'			=>	'Year', //Required.  Year, month, week
			'billing_frequency'			=>	'1',	//Required.  Number of times to bill per period
			'total_billing_cycles'		=>	'3', //Total # of times the customer will be billed.
			'amt'						=>	'25.00',	//Required.  Amount to bill on a recurring basis.
			'trial_billing_frequency'	=>	'Year', //Set this if you want a trial.  Year, month, week.
			'trial_billing_cycles'		=>	'1', //Total # of times you want the customer to be billed at the trial rate.
			'trial_amt'					=>	'5.00',	//The trial rate.
			'currency_code'				=>	'USD', //ie USD
			'shipping_amt'				=>	'0.00', //Total of shipping alone.
			'tax_amt'					=>	'0.00', //Total of tax alone.
			'initial_amt'				=>	'0.00',	//billed immediately upon profile creation
			'failed_init_action'		=>	'Continue',	//What to do if the initial bill failes.  Continue or Cancel.
			'inv_num'					=>	'INV-NUM',
			'ship_to_first_name'		=>	'Calvin', //Name of person being shipped to
			'ship_to_last_name'			=>	'Froedge',
			'ship_to_street'			=>	'151 Somewhere Street',
			'ship_to_street2'			=>	'Suite A',
			'ship_to_city'				=>	'Somewhere',
			'ship_to_state'				=>	'KY',
			'ship_to_zip'				=>	'42167',
			'ship_to_country'			=>	'United States',
			'ship_to_phone_num'			=>	'(801) 754 4466',
			'ship_to_company'			=>	'Some Company',
			'cc_type'					=>	'Visa',	//Required.  Credit card type.
			'cc_number'					=>	'4111111111111111',	//Required.  Credit card number.
			'cc_exp'					=>	'022015',	//Required.  Credit card expiration date.
			'cc_code'					=>	'203',	//Required.  Credit Card CVV code.
			'email'						=>	'calvinsemail@gmail.com',
			'identifier'				=>	'YOUR-IDENTIFIER',
			'country'					=>	'US',	//Required.  Buyer's country code.
			'business_name'				=>	'The Business Name',
			'salutation'				=>	'Mr.',
			'first_name'				=>	'Calvin',
			'middle_name'				=>	'P',
			'last_name'					=>	'Froedge',
			'suffix'					=>	'PIMP',
			'street'					=>	'317 Kublashayev',  //Required.  Buyer's street address.
			'street2'					=>	'',
			'city'						=>	'Simferople',	//Required.  Buyer's city.
			'state'						=>	'CR',	//Required.  Buyer's state or province.
			'postal_code'				=>	'UKR',	//Required.  Buyer's postal code.
			'phone'						=>	'(801) 754 4466',
			'fax'						=>	'(801) 754 4466',
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