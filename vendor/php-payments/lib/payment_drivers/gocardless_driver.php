<?php

class Gocardless_Driver extends Payment_Driver
{
	/*
	 * The endpoint to use
	*/
	private $_endpoint;

	/*
	 * The PHP Payments method
	*/ 
	private $_lib_method;

	/*
	 * Config array
	*/
	private $_config;

	/*
	 * Constructor
	*/
	public function __construct($config)
	{
		Payment_Utility::load('file', 'vendor/gocardless/lib/gocardless');	
		$this->_config = $config;
	}

	/**
	 * Caller Magic Method
	 *
	 * @param	string
	 * @param	array
	 * @return	object
	*/
	public function __call($method, $params)
	{
		GoCardless::$environment = ($this->_config['mode'] == 'test') ? 'sandbox' : 'production';
		$account_details = array(
		  'app_id'        => $this->_config['app_identifier'],
		  'app_secret'    => $this->_config['app_secret'],
		  'merchant_id'   => $this->_config['id'],
		  'access_token'  => $this->_config['access_token']
		);
		GoCardless::set_account_details($account_details);

		$args = $params[0];
		$this->_lib_method = $method;
		list($api, $api_method, $params_ready) = $this->_build_request($args);

		try
		{
			$raw = $api::$api_method($params_ready);
			return $this->_parse_response($raw);
		}
		catch(Exception $e)
		{
			return Payment_Response::instance()->gateway_response(
				'failure',
				$method.'_gateway_failure',
				$e->getMessage()
			);
		}
	}

	/**
	 * Maps Methods to Details Particular to Each Request for that Method
	 *
	 * @return array
	 */
	public function method_map()
	{
		$method_map = array(
			'oneoff_payment_button' => array(
				'api' => 'GoCardless',
				'method' => 'new_bill_url',
				'required' => array(
					'amt',
					'desc'
				),
				'keymatch' => array(
					'amt' => 'amount',
					'desc' => 'name'
				),
				'is_button' => true
			),
			'recurring_payment_button' => array(
				'api' => 'GoCardless',
				'method' => 'new_subscription_url',
				'required' => array(
					'amt',
					'name',
					'billing_period',
					'billing_frequency'
				),
				'keymatch' => array(
					'amt' => 'amount',
					'desc' => 'name',
					'billing_frequency' => 'interval_length',
					'billing_period' => 'interval_unit'
				),
				'is_button' => true
			),
			'get_transaction_details' => array(
				'api' => 'GoCardless_Bill',
				'method' => 'find',
				'required' => array(
					'identifier'
				)
			),
			'get_recurring_profile' => array(
				'api' => 'GoCardless_Subscription',
				'method' => 'find',
				'required' => array(
					'identifier'
				)
			)
		);

		return $method_map;
	}

	/**
	 * Builds the Request
	 *
	 * @param	array
	 * @return	array
	 */
	protected function _build_request($params)
	{
		$method_map = $this->method_map();
		$m = $method_map[$this->_lib_method];

		if(count($params) == 1 && array_key_exists('identifier', $params))
		{
			$return_params = $params;
		}
		else
		{
			$return_params = array();
			foreach($m['keymatch'] as $k=>$v)
			{
				$return_params[$v] = $params[$k];
			}
		}
		
		$this->_is_button = (isset($m['is_button']) && $m['is_button'] == true) ? true : false;

		return array(
			$m['api'],
			$m['method'],
			$return_params
		);
	}

	/**
	 * Parse the Response and then Delegate to the Response Object
	 *
	 * @param	object
	 * @return	object
	 */
	protected function _parse_response($response)
	{
		if($this->_is_button)
		{
			return Payment_Response::instance()->local_response(
				'success',
				$this->_lib_method.'_success',
				$response
			);
		}
		else
		{
			/*return Payment_Response::instance()->gateway_response(
				'success',
				$this->_lib_method.'_success',
				$response
			);*/ //Will be integrated when testing details are received
			var_dump($response);exit;
		}
	}
}