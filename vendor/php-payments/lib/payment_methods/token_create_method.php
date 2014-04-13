<?php

class Token_Create_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Stores card data on payment server and gives you a secure identifier for the card which can be used to create payments later.";

	public function __construct()
	{
		$this->_params = array(
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
			'desc'				=>	'The transaction description', //Description for the transaction
			'custom'			=>	'Anything you want to put here', //Free form text field
			'po_num'			=>	'YOUR-PO-NUMBER',
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