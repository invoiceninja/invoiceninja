<?php

class Eway_Driver Extends Payment_Driver
{
	/**
	 * The API Endpoint
	 */
	private $_api_endpoint;

	/**
	 * Testing API Endpoint
	 */
	private $_api_endpoint_test = 'https://www.eway.com.au/gateway/xmltest/testpage.asp';

	/**
	 * Production API Endpoint
	 */
	private $_api_endpoint_production = 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';

	/**
	 * Constructor method
	*/		
	public function __construct($config)
	{
		$this->_api_endpoint = ($config['mode'] == 'test') ? $this->_api_endpoint_test : $this->_api_endpoint_production;

		$this->_api_settings = array(
			'api_cid'		=> $config['api_cid'],
			'xml_version'	=> '1.0',
			'encoding'		=> 'utf-8',
			'xml_schema'	=> ''
		);
	}

	/**
	 * Caller Magic Method
	*/
	public function __call($method, $params)
	{
		$this->_lib_method = $method;

		$args = $params[0];
		$request = $this->_build_request($args);
		$response_raw = Payment_Request::curl_request($this->_api_endpoint, $request);	
		
		return $this->_parse_response($response_raw);
	}

	/**
	 * Method Map
	*/
	public function method_map()
	{
		$map = array(
				'oneoff_payment' => array(
					'api' => 'authCaptureTransaction',
					'required' => array(
						'amt',
						'first_name',
						'last_name',
						'email',
						'cc_number',
						'cc_exp',
						'cc_code',
						'street',
						'postal_code',
						'desc'
					),
					'keymatch' => array(
						'amt' => 'ewayTotalAmount',
						'first_name' => 'ewayCardHoldersName&ewayCustomerFirstName',
						'last_name' => 'ewayCardHoldersName&ewayCustomerLastName',
						'cc_number' => 'ewayCardNumber',
						'cc_exp' => 'ewayCardExpiryMonth,ewayCardExpiryYear',
						'cc_code' => 'ewayCVN',
						'email' => 'ewayCustomerEmail',
						'street' => 'ewayCustomerAddress',
						'postal_code' => 'ewayCustomerPostcode',
						'desc' =>	'ewayCustomerInvoiceDescription',
						'identifier' => 'ewayCustomerInvoiceRef'
					)
				)
		);

		return $map;
	}

	/**
	 * Builds a request
	 * @param	array	array of params
	 * @param	string	the api call to use
	 * @param	string	the type of transaction
	 * @return	array	Array of transaction settings
	*/	
	protected function _build_request($params, $transaction_type = NULL)
	{
		$nodes = array('ewaygateway' => array());
		$root = &$nodes['ewaygateway'];

		$root['ewayCustomerID'] = $this->_api_settings['api_cid'];
		
		$root['ewayTrxnNumber'] = ' ';
		$root['ewayOption1'] = ' ';
		$root['ewayOption2'] = ' ';
		$root['ewayOption3'] = ' ';
		if(!isset($params['identifier'])) $root['ewayCustomerInvoiceRef'] = mt_rand(10, 10000);
	
		$map = $this->method_map();
		foreach($params as $k=>$v)
		{
			$l = $this->_lib_method;
			if(isset($map[$l]['keymatch'][$k]))
			{
				$key = $map[$l]['keymatch'][$k];

				if(strpos($key, ',') !== false)
				{
					$ex = explode(',', $key);

					if($k == 'cc_exp')
					{
						$month = substr($v, 0, 2);
						$year = substr($v, -2, 2);

						$root[$ex[0]] = $month;
						$root[$ex[1]] = $year;
					}
				}
				else if(strpos($key, '&') !== false)
				{
					$ex = explode('&', $key);
					foreach($ex as $exk)
					{
						$root[$exk] = $v;
					}
				}
				else
				{
					$root[$key] = $v;
					continue;
				}

				if(isset($root[$key]))
				{
					$root[$key] .= $v;
				}
			}
			else
			{
				error_log("The $k param for the $l method in the Eway Driver does not exist.");
			}
		}

		$request = Payment_Request::build_xml_request(
			$this->_api_settings['xml_version'],
			$this->_api_settings['encoding'],
			$nodes
		);

		return $request;	
	}

	/**
	 * Parses the XML response from the gateway and returns success or failure, along with details
	 *
	 * @param	object
	 * @return	object
	 */		
	protected function _parse_response($response)
	{
		$details = (object) array();

		$as_array = Payment_Utility::arrayize_object($response);
		
		if($as_array['ewayTrxnStatus'] == 'True')
		{
			$details->identifier = $as_array['ewayTrxnNumber'];
			
			return Payment_Response::instance()->gateway_response(
				'Success',
				$this->_lib_method.'_success',
				$details
			);
		}
		else
		{
			$details->reason = $as_array['ewayTrxnError'];
			$details->gateway_response = $as_array;
			
			return Payment_Response::instance()->gateway_response(
				'Failure',
				$this->_lib_method.'_gateway_failure',
				$details
			);			
		}
	}
}