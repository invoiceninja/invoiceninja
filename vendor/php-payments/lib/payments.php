<?php

/**
* PHP-Payments
*
* @package Payments
* @author Calvin Froedge (www.calvinfroedge.com)
* @created 07/02/2011
* @refactored 02/11/2012
* @license http://www.opensource.org/licenses/mit-license.php
*/

class PHP_Payments
{
	/**
	 * Config Property
	*/
	public $config;

	/**
	 * The constructor function.
	 */	
	public function __construct($config = array())
	{
		$this->config = $config;
		$this->_bootstrap();
		Payment_Utility::connection_is_secure($this->config);
	}
	
	/** 
	 * Sets up Autoload, Sets Some Properties We Need
	 *
	 * @return	void
	*/
	private function _bootstrap()
	{	
		include_once('payment_utility.php');
		spl_autoload_register(array(new Payment_Utility, 'class_autoload'));
		$this->config = array_merge($this->config, Payment_Utility::load('config', 'payments')); //Note that here, config file configuration rules are merged with what was passed in the constructor.  If there is a conflict, what was passed in the constructor is used.
	}

	/**
	 * Make a call to a gateway. Uses other helper methods to make the request.
	 *
	 * @param	string	The payment method to use
	 * @param	array	$params[0] is the gateway, $params[1] are the params for the request.  $params[2] is a config array for the driver.
	 * @return	object	Should return a success or failure, along with a response.
	 */		
	public function __call($method, $params)
	{
		$gateway = $params[0].'_Driver';
		$args = $params[1];
		$config = (isset($params[2])) ? $params[2] : @Payment_Utility::load('config', 'drivers/'.$params[0]); //Load the driver config if not passed in constructor
		$config['mode'] = (isset($this->config['mode']) && $this->config['mode'] === 'test') ? 'test' : 'production';

		try {
			$driver = new $gateway($config);
		} 
		catch (Exception $e) {
			return Payment_Response::instance()->local_response('failure', 'not_a_module', $e->getMessage());
		}
		
		$method_map = $driver->method_map();

		if(!isset($method_map[$method])) return Payment_Response::instance()->local_response('failure', 'not_a_method');

		//Make sure params are in expected format, make sure required have been provided
		$validation_check = Payment_Validator::validate($method, $args, $method_map[$method]['required']);

		return ($validation_check === true) ? $driver->$method($args) : $validation_check;
	}
}