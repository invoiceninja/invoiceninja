<?php

class Recurring_Payment_Button_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Generates an HTML button which redirects a user to a hosted payments page.  Similar to Recurring_Payment.";

	public function __construct()
	{
		$this->_params = array(
			'amt'						=>	'14.00',	//Amount for the payment
			'desc'						=>	'A description for the transaction', //A description for the transaction
			'trial_billing_period'		=>  'Month',
			'trial_billing_frequency'	=>	'1', //Set this if you want a trial.  Year, month, week, day.
			'trial_billing_cycles'		=>	'1', //Total # of times you want the customer to be billed at the trial rate.
			'trial_amt'					=>	'10.00',	//The trial rate.
			'profile_start_date' 		=>  '2012-07-18',
			'billing_period' 			=>  'Month',
			'billing_frequency' 		=>  '1',
			'total_billing_cycles' 		=>  '12'
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