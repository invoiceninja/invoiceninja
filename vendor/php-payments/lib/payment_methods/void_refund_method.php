<?php

class Void_Refund_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Second guess a previous void operation.   Go ahead and settle.  Admittedly a weird API operation.";

	public function __construct()
	{
		$this->_params = array(
			'cc_number' => '4111111111111111',
			'cc_type' 	=> 'Visa',
			'cc_code' 	=> '203',
			'cc_exp'	=> '022012',
			'amt'		=> '14.00',
			'first_name' => 'Calvin',
			'last_name' => 'Froedge',
			'phone' => '(801) 754 4466',
			'email' => 'calvintest@gmail.com',
			'street' => '311 Something Street',
			'city' => 'Cookeville',
			'state' => 'TN',
			'country' => 'US',
			'postal_code' => '38501',
			'identifier' => 'IDTEST2039'	
		);
	}

	public function get_params()
	{
		return $this->_params;
	}

	public function get_description()
	{
		return $this->_descrip;
	}
}