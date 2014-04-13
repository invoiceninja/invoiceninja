<?php

class Cancel_Recurring_Profile_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "This method cancels recurring billing for a particular recurring billing profile.";

	public function __construct()
	{
		$this->_params = array(
			'identifier'	=>	'PROFILE-23928239', //Required.  Should have been returned when you created the profile.
			'note'			=>	'The reason for cancelling this profile', //This is just a note.
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