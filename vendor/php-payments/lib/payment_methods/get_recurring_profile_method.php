<?php

class Get_Recurring_Profile_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Gets a particular recurring profile and returns an object with all the details about it.";

	public function __construct()
	{
		$this->_params = array(
			'identifier'	=>	'PROFILE-2923849', //Required.  Should have been returned when you created the profile.
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