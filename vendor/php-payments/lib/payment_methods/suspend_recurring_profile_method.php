<?php

class Suspend_Recurring_Profile_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Stop billing a reccurring profile.";

	public function __construct()
	{
		$this->_params = array(
			'identifier'	=>	'IDENTIFIER-2392032', //Required.  Should have been returned when you created the profile.
			'note'			=>	'This is the note', //This is just a note.
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