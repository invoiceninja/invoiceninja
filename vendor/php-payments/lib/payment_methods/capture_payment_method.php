<?php

class Capture_Payment_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "This method completes a transaction which was previously auhorized.";

	public function __construct()
	{
		$this->_params = array(
			'ip_address'		=>	'191.239.29.23',	//IP address of purchaser
			'cc_type'			=>	'Visa',	//Visa, MasterCard, Discover, Amex
			'cc_number'			=>	'4111111111111111', //Credit card number
			'cc_exp'			=>	'022012', //Must be formatted MMYYYY
			'cc_code'			=>	'203', //3 or 4 digit cvv code
			'custom'			=>	'This is some custom param', //Free form text field
			'inv_num'			=>	'1003', //Invoice number
			'note'				=>	'Some note to include with the capture' //A note for the transaction
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