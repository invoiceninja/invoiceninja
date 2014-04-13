<?php

class Reference_Payment_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Similar to Oneoff_Payment and payment vaulting alike, but allows a user to make a payment just by reference a successfully completed payment which occurred in the past.";

	public function __construct()
	{
		$this->_params = array(
			'identifier' => 'PREVIOUS-TRANS-1923932',
			'amt' => '30.00'
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