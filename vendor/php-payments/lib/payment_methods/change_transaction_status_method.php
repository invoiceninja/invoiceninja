<?php

class Change_Transaction_Status_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "This method is used to alter a transaction's status to Accept or Deny.";

	public function __construct()
	{
		$this->_params = array(
			'identifier'			=>	'TRANS-239239',  //Required. Unique identifier for the transaction, generated from a previous transaction.
			'action'				=>	'Accept'  //Required.  Should be Accept or Deny.
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