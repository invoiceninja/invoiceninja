<?php

class Stripe_Driver extends Payment_Driver
{
	/**
	 * URL to Use
	*/
	private $_api_url;

	/**
	 * Endpoint to Send the Query To
	*/
	private $_endpoint;

	/**
	 * The particular API which is being used
	*/
	private $_api;

	/**
	 * The particular API method which is being used
	*/
	private $_api_method;

	/**
	 * The PHP Payments library method
	*/
	private $_lib_method;

	/**
	 * Stores Settings for the Transaction
	*/
	private $_settings;

	/**
	 * Whether to Make the Final Call in Object Scope
	 */
	private $_object_scoped;

	/**
	 * The Constructor Function
	*/
	public function __construct($config)
	{
		foreach($config as $k=>$v)
		{
			$this->_settings[$k] = $v;
		}
	}

	/**
	 * Call Magic Method
	 */
	public function __call($method, $params)
	{	
		$args = $params[0];
		
		//If you pass 10 to stripe you don't charge 10 dollars, you charge 10 cents
		if(isset($args['amt']) && strpos($args['amt'], '.') === false){
			$args['amt'] .= ".00";
		}

		if(isset($args['amt'][0]) && $args['amt'][0] == '.'){
			unset($args['amt'][0]);
		}

		$method_map = $this->method_map();
		
		$this->_api = $method_map[$method]['api'];
		$this->_api_method = (isset($method_map[$method]['method'])) ? $method_map[$method]['method'] : '';
		$this->_lib_method = $method;
		
		list($api, $api_method, $params_ready) = $this->_build_request($args);

		try {
			//If the only param is an id, just use that as a string instead of sending an array
			if(count($params_ready) == 1 && isset($params_ready['id'])) $params_ready = $params_ready['id'];

			$response_raw = ($this->_object_scoped) ? $api->$api_method() : $api::$api_method($params_ready);
		}
		catch (Exception $e) {
			return Payment_Response::instance()->gateway_response(
				'failure',
				$method.'_gateway_failure',
				$e->getMessage()
			);
		}

		return $this->_parse_response($response_raw);
	}

	/**
	 * Maps PHP-Payments Methods to Details Particular to Each Request for that Method
	 */
	public function method_map()
	{
		$map = array(
			'oneoff_payment' => array(
				'api' => 'Stripe_Charge',
				'method' => 'create',
				'required' => array(
					'cc_number',
					'cc_exp',
					'amt',
					'currency_code'
				),
				'keymatch' => array(
					'amt' => 'amount',
					'currency_code' => 'currency',
					'identifier' => 'customer',
					'cc_number' => 'card["number"]',
					'cc_exp' => 'card["exp_month,exp_year"]',
					'cc_code' => 'card["cvc"]',
					'first_name' => 'card["name"]',
					'last_name' => 'card["name"]',
					'street' => 'card["address_line1"]',
					'street2' => 'card["address_line2"]',
					'postal_code' => 'card["address_zip"]',
					'state' => 'card["address_state"]',
					'country' => 'card["address_country"]',
					'desc' => 'description'
				)
			),
			'get_transaction_details' => array(
				'api' => 'Stripe_Charge',
				'method' => 'retrieve',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'id'
				)
			),
			'refund_payment' => array(
				'api' => 'Stripe_Charge',
				'method' => 'retrieve->refund',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'id',
					'amt' => 'amount'
				)
			),
			'token_create' => array(
				'api' => 'Stripe_Token',
				'method' => 'create',
				'required' => array(
					'cc_number',
					'cc_exp'
				),
				'keymatch' => array(
					'identifier' => 'customer',
					'cc_number' => 'card["number"]',
					'cc_exp' => 'card["exp_month,exp_year"]',
					'cc_code' => 'card["cvc"]',
					'first_name' => 'card["name"]',
					'last_name' => 'card["name"]',
					'street' => 'card["address_line1"]',
					'street2' => 'card["address_line2"]',
					'postal_code' => 'card["address_zip"]',
					'state' => 'card["address_state"]',
					'country' => 'card["address_country"]',
					'desc' => 'description'
				)
			),
			'customer_create' => array(
				'api' => 'Stripe_Customer',
				'method' => 'create',
				'required' => array(
					'desc',
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'card',
					'desc' => 'description'
				)
			),
			'customer_charge' => array(
				'api' => 'Stripe_Charge',
				'method' => 'create',
				'required' => array(
					'amt',
					'currency_code',
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'customer',
					'amt' => 'amount',
					'currency_code' => 'currency'
				)
			)
		);
		return $map;
	}

	/**
	 * Builds the Request
	 */
	protected function _build_request($params)
	{
		$stripe = Payment_Utility::load('file', 'vendor/stripe/lib/Stripe');
		
		Stripe::setApiKey($this->_settings['api_key']);

		$api = $this->_api;

		if(strpos($this->_api_method, '->') === false)
		{
			$method = $this->_api_method;
		}
		else
		{
			$ex = explode('->', $this->_api_method);

			$m1 = $ex[0];
			$method = $ex[1];

			$api = $api::$m1($params['identifier']);
			$this->_object_scoped = true;
		}

		$params_ready = $this->_match_params($params);

		//Make the call to Stripe API
		return array($api, $method, $params_ready);
	}

	/**
	 * Match the Params
	 */
	private function _match_params($params)
	{
		$method_map = $this->method_map();
		$l = $this->_lib_method;
		$params_ready = array();

		$matcher = $method_map[$l]['keymatch'];
		foreach($params as $k=>$v)
		{
			if(isset($matcher[$k]))
			{
				if(strpos($matcher[$k], '["') !== false)
				{
					$ex = explode('["', $matcher[$k]);
					$arr = $ex[0];
					$arrk = str_replace('"]', '', $ex[1]);

					if(!isset($params_ready[$arr])) $params_ready[$arr] = array();
				
					if(strpos($arrk, ',') !== false && $k === 'cc_exp')
					{
						$cex = explode(',', $arrk);

						$d_mm = substr($v, 0, 2);
						$d_yyyy = substr($v, 2, 4);

						$params_ready[$arr][$cex[0]] = $d_mm; //set the month
						$params_ready[$arr][$cex[1]] = $d_yyyy; //set the year
					}
					else
					{
						$params_ready[$arr][$arrk] = $v;
					}
				}
				else
				{
					$key = $matcher[$k];
					$val = $v;

					//Remove the decimal place to format money in cents
					if($k == 'amt') $val = str_replace('.', '', $val);

					$params_ready[$key] = $val;
				}
			}
			else
			{
				error_log("$k is not a valid param for this method in this driver");
			}
		}

		return $params_ready;
	}

	/**
	 * Parse the response from the server
	 *
	 * @param	array
	 * @return	object
	 */		
	protected function _parse_response($response)
	{
		$details = (object) array();

		if(isset($response->id)) $details->identifier = $response->id;

		$details->timestamp = $response->created;
		$details->gateway_response = $response;

		return Payment_Response::instance()->gateway_response(
			'success',
			$this->_lib_method.'_success',
			$details
		);
	}
}
