<?php

class Recurring_Bill_Outstanding_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Bill a particular recurring profile for an amount which previously could not be billed.";

	public function __construct()
	{
		$this->_params = array(
			'identifier'	=> 'PROFILE-IDENTIFIER', //Required.  Should have been returned when you created the profile.
			'amt'			=> '35.00', //The outstanding amount to bil.  Cannot exceed total owed.
			'note'			=> 'Why the profile is being billed' //This is just a note.
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