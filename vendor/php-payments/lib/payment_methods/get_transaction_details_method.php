<?php

class Get_Transaction_Details_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Gets the logs for a particular transaction from the gateway.";

	public function __construct()
	{
		$this->_params = array(
			'identifier'	=>	'TRANS-239238', //Required.  Should have been returned when you created the transaction.
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