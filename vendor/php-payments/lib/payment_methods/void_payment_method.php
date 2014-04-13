<?php

class Void_Payment_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Tell the gateway not to settle a transaction which has not yet been settled.  Similar to a refund, but no monies have been billed to the user yet.";

	public function __construct()
	{
		$this->_params = array(
			'identifier'			=>	'ID2930238',	//Required. Unique identifier for the transaction, generated from a previous authorization.
			'note'					=>	'Some note to tell why you voided it.' //An optional note to be submitted along with the request.
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