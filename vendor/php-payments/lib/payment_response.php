<?php

class Payment_Response
{
	/**
	 * The Language to Return the Response In
	*/
	private static $_language = 'english';

	/**
	 * Holds the Instance
	*/
	private static $_instance = false;

	/**
	 * Response Details Array
	*/
	private static $_response_details;

	/**
	 * Response Messages Array
	*/
	private static $_response_messages;

	/**
	 * Response Codes Array
	*/
	private static $_response_codes;

	//Make this a Singleton
	private function __construct()
	{
		self::$_response_details = Payment_Utility::load('lang', self::$_language.'/response_details');
		self::$_response_messages = Payment_Utility::load('lang', self::$_language.'/response_messages');
		self::$_response_codes = Payment_Utility::load('config', 'payments', 'response_codes');
	}

	/**
 	  * Instance Manager
	 */
	public static function instance()
	{
		self::$_instance = (self::$_instance !== false) ? self::$_instance : new Payment_Response();
		
		return self::$_instance;
	}

	/**
	 * Set the Language
	 */
	public static function set_language($_language, $value)
	{
		self::$_language = $value;	
	}

	/**
	 * Get the Language
	*/
	public static function get_language()
	{
		return self::$_language;
	}

	/**
	 * Returns a local response
	 *
	 * @param 	string	can be either 'Success' or 'Failure'
	 * @param	string	the response used to grab the code / message
	 * @param	mixed	can be string or null. 
	 * @return	object	
	*/	
	public function local_response($status, $response, $details = null)
	{
		$status = strtolower($status);

		if(!is_null($details))
		{
			$details_msg = (isset(self::$_response_details[$details])) ? self::$_response_details[$details] : $details;
		}

		return (object) array
		(
			'type'				=>	'local_response',
			'status' 			=>	$status, 
			'response_code' 	=>	self::$_response_codes[$response], 
			'response_message' 	=>	self::$_response_messages[$response],
			'details'			=>	(isset($details_msg)) ? $details_msg : self::$_response_details['no_details']
		);				
	}

	/**
	 * Returns a gateway response
	 *
	 * @param 	string	can be either 'Success' or 'Failure'
	 * @param	string	the response used to grab the code / message
	 * @param	mixed	can be string or null. 
	 * @return	object	
	*/	
	public function gateway_response($status, $response, $details)
	{	
		return (object) array
		(
			'type'				=>	'gateway_response',
			'status' 			=>	$status, 
			'response_code' 	=>	self::$_response_codes[$response], 
			'response_message' 	=>	self::$_response_messages[$response],
			'details'			=>	$details
		);		
	}	
	
}
