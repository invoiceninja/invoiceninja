<?php

class Amazon_SimplePay_Driver extends Payment_Driver
{
	/**
	 * The PHP-Payments Library Method Being Utilized
	*/
	private $_lib_method;

	/**
	 * The Config Array
	*/
	private $_config;

	/**
	 * If the Request Returns a Button
	*/
	private $_is_button;

	/**
	 * The Mode.  Either "sandbox" or "prod"
	*/
	private $_mode;

	/**
	 * Class Constructor
	*/
	public function __construct($config)
	{
		$this->_mode = ($config['mode'] == 'test') ? 'sandbox' : 'prod';
		$this->_config = $config;
	}

	/**
	 * The Caller Magic Method
	*/
	public function __call($method, $params)
	{
		$this->_lib_method = $method;

		$args = $params[0];

		try{
			$request = $this->_build_request($args);
		}
		catch(Exception $e){
			return Payment_Response::instance()->local_response(
				'failure',
				$e->getMessage()
			);
		}

	 	return $this->_parse_response($request);
	}

	/**
	 * Maps PHP-Payments Keys to Gateway
	*/
	public function method_map()
	{
		$map = array(
			'oneoff_payment_button' => array(
				'api' => 'ButtonGenerator',
				'method' => 'GenerateForm',
				'required' => array(
					'amt',
					'desc',
					'currency_code'
				),
				'keymatch' => array(
					'amount' => 'amount',
					'desc' => 'description',
					'identifier' => 'referenceId'
				),
				'static' => array(
					'signature_method' => 'HmacSHA256',
					'abandon_url' => $this->_config['abandon_url'],
					'return_url' => $this->_config['return_url'],
					'immediate_return' => $this->_config['immediate_return'],
					'process_immediate' => $this->_config['process_immediate'],
					'ipn_url' => $this->_config['ipn_url'],
					'collect_shipping_address' => $this->_config['collect_shipping_address']
				)
			)
		);

		return $map;
	}

	/**
	 * Build the Request
	 *
	 * @param	array	Params array
	 * @return	mixed	array for requests, strings for buttons
	*/
	protected function _build_request($params)
	{
		$map = $this->method_map();
		$l = $this->_lib_method;

		$api = $map[$l]['api'];
		$method = $map[$l]['method'];
		$static = $map[$l]['static'];

		if($api == 'ButtonGenerator')
		{
			Payment_Utility::load('file', 'vendor/amazon_simplepay/ButtonGenerationWithSignature/src/ButtonGenerator');
			$this->_is_button = true;

			ob_start();
			$api::$method(
				$this->_config['access_key'],
				$this->_config['secret_key'],
				$params['currency_code'] . ' ' . $params['amt'],
				$params['desc'],
				(isset($params['identifier'])) ? $params['identifier'] : '',
				$static['immediate_return'],
				$static['return_url'],
				$static['abandon_url'],
				$static['process_immediate'],
				$static['ipn_url'],
				$static['collect_shipping_address'],
				$static['signature_method'],
				$this->_mode
			);
			$string = ob_get_clean();
			return $string;
		}
	}

	/**
	 * Parse the Response
	 *
	 * @param 	array	Raw response
	 * @return	object	Payment_Response
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
	}
}