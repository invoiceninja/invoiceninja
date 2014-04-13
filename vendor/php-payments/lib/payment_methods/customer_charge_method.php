<?php

class Customer_Charge_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Charge a customer";

	public function __construct()
	{
		$this->_params = array(
			'amt' 			=> 	'20.00',
			'currency_code' => 	'usd',
			'identifier'	=>	'PROFILE-2923849' //Some identifier for the transaction
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
