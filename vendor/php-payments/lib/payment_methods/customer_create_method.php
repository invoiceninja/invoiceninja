<?php

class Customer_Create_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Create a customer instance which is stored in the gateway.";

	public function __construct()
	{
		$this->_params = array(
			'desc'			=>  'Some description',
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
