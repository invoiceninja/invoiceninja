<?php

class Authorize_Payment_Button_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "This method generates a button which is used to provide the user a link to a hosted payments page on which they can enter their payment information.  This method is similar to Authorize_Payment.";

	public function __construct()
	{
		$this->_params = array(
			'amt'		=>	'2.00',	//Amount for the payment
			'desc'		=>	'Click here to buy me', //A description for the button
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