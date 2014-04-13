<?php

class Payment_Validator
{
	public function __construct($payments)
	{
		$this->utility = $payments['utility'];
		$this->payments_config = $payments['payments_config'];
		$this->lang = $payments['lang'];
	}

	/**
	 * Make sure params are as expected
	 *
	 * @param	array	array of params to check to ensure proper formatting
	 * @param	array	array of required params
	 * @return	mixed	Will return TRUE if all pass.  Will return an object if a param is bad.
	 */			
	public static function validate($method, $params, $required_params)
	{
		//Append _method to method name
		$method = $method."_method";

		//We'll need this later
		$lang = Payment_Utility::load('lang', 'english/response_details');

		//Ensure no invalid methods were passed
		include_once('payment_methods/'.$method.'.php');
		$m = new $method;

		$method_params = $m->get_params();

		$bad_params = array();
		foreach($params as $k=>$v)
		{
			if(!isset($method_params[$k]))
			{
				$bad_params[] = "$k " . $lang['is_not_a_param'];
			}
		}

		if(count($bad_params) > 0)
		{
			return Payment_Response::instance()->local_response(
				'failure',
				'invalid_input',
				implode(', ', $bad_params)
			);
		}

		//Ensure no required params are missing
		$missing = array();
		foreach($required_params as $k=>$v)
		{	
			if(!array_key_exists($v, $params) OR empty($params[$v]) OR is_null($params[$v]) OR $params[$v] == ' ')
			{
				$key = 'missing_'.$v;
				if(isset($lang[$key]))
				{
					$missing[] = $lang[$key];
				}
				else
				{
					error_log("$key does not exist in response message language file.");
					$missing[] = "$v is required but was not provided";
				}
			}
		}

		if(count($missing) > 0)
		{
			return Payment_Response::instance()->local_response(
				'failure', 
				'required_params_missing', 
				implode(', ', $missing)
			);					
		}
		
		//Ensure dates match MMYYYY format
		if(array_key_exists('cc_exp', $params))
		{
			$exp_date = $params['cc_exp'];
			$m1 = $exp_date[0];
			
			if(strlen($exp_date) != 6 OR !is_numeric($exp_date) OR $m1 > 1)
			{
				return Payment_Response::instance()->local_response(
					'failure', 
					'invalid_input',
					'invalid_date_format'
				);
			}
		}
		
		//Ensure billing period is submitted in normalized form
		if(array_key_exists('billing_period', $params))
		{
			$accepted_billing_period = array(
				'Month',
				'Day',
				'Week',
				'Year'
			);
			
			if(!in_array($params['billing_period'], $accepted_billing_period))
			{
				return Payment_Response::instance()->local_response(
					'failure', 
					'invalid_input',
					'invalid_billing_period'
				);			
			}
		}
		
		return TRUE;
	}
}
