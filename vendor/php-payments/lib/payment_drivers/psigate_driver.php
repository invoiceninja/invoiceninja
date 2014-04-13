<?php

class Psigate_Driver extends Payment_Driver
{	
	/**
	 * The config object
	*/
	public $config;

	/**
	 * The endpoint for a particular transaction
	*/
	private $_api_endpoint;	

	/**
	 * The settings for a particular transaction
	*/
	private $_api_settings;	

	/**
	 * The api method to use
	*/
	private $_api_method;	
	
	/**
	 * The library method being used
	*/
	private $_lib_method;

	/**
	 * Constructor method
	*/		
	public function __construct($config)
	{
		$this->_api_endpoint = ($config['mode'] == 'test') ? $config['api_endpoint'.'_test']: $config['api_endpoint'.'_production'];	
		
		$this->_api_settings = array(
			'cid'			=> $config['api_cid'],
			'store_id'		=> $config['api_username'],
			'pass_phrase'	=> $config['api_password'],
			'xml_version'	=> '1.0',
			'encoding'		=> 'utf-8',
			'xml_schema'	=> '',
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
			'oneoff_payment'	=>	array(
				'api' => 'Order',
				'method' => '0',
				'required' => array(
					'cc_number',
					'cc_exp',
					'cc_code',
					'amt'
				),
				'keymatch' => array(
					'amt' => 'Subtotal',
					'first_name' => 'Bname',
					'last_name' => 'Bname',
					'company' => 'Bcompany',
					'street' => 'Baddress1',
					'city' => 'Bcity',
					'state' => 'Bprovince',
					'country' => 'Bcountry',
					'ship_to_first_name' => 'Sname',
					'ship_to_last_name' => 'Sname',
					'ship_to_company' => 'Scompany',
					'ship_to_street' => 'Saddress1',
					'ship_to_city' => 'Scity',
					'ship_to_state' => 'Sprovince',
					'ship_to_country' => 'Scountry',
					'cc_number' => 'CardNumber',
					'cc_exp' => 'CardExpMonth,CardExpYear', //ex 11/15
					'cc_code' => 'CardIDNumber',
					'phone' => 'Phone',
					'fax' => 'Fax',
					'email' => 'Email',
					'note' => 'Comments',
					'tax_amt' => 'Tax1',
					'shipping_amt' => 'ShippingTotal',
					'ip_address' => 'CustomerIP'
				),
				'static' => array(
					'PaymentType' => 'CC',
					'CardAction' => '0'
				)
			),
			'authorize_payment'	=>	array(
				'api' => 'Order',
				'method' => '1',
				'required' => array(
					'cc_number',
					'cc_exp',
					'cc_code',
					'amt'
				),
				'keymatch' => array(
					'amt' => 'Subtotal',
					'first_name' => 'Bname',
					'last_name' => 'Bname',
					'company' => 'Bcompany',
					'street' => 'Baddress1',
					'city' => 'Bcity',
					'state' => 'Bprovince',
					'country' => 'Bcountry',
					'ship_to_first_name' => 'Sname',
					'ship_to_last_name' => 'Sname',
					'ship_to_company' => 'Scompany',
					'ship_to_street' => 'Saddress1',
					'ship_to_city' => 'Scity',
					'ship_to_state' => 'Sprovince',
					'ship_to_country' => 'Scountry',
					'cc_number' => 'CardNumber',
					'cc_exp' => 'CardExpMonth,CardExpYear', //ex 11/15
					'cc_code' => 'CardIDNumber',
					'phone' => 'Phone',
					'fax' => 'Fax',
					'email' => 'Email',
					'note' => 'Comments',
					'tax_amt' => 'Tax1',
					'shipping_amt' => 'ShippingTotal',
					'ip_address' => 'CustomerIP'
				),
				'static' => array(
					'CardAction' => '1',
					'PaymentType' => 'CC'
				)
			),
			'capture_payment'	=>	array(
				'api' => 'Order',
				'method' => '2',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'OrderId'
				),
				'static' => array(
					'CardAction' => '2',
					'PaymentType' => 'CC'
				)
			),
			'void_payment'	=>	array(
				'api' => 'Order',
				'method' => '9',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'OrderId'
				),
				'static' => array(
					'CardAction' => '9',
					'PaymentType' => 'CC'
				)
			),	
			'refund_payment'=>	array(
				'api' => 'Order',
				'method' => '3',
				'required' => array(
					'identifier',
					'identifier_2',
					'amt'
				),
				'keymatch' => array(
					'identifier' => 'OrderId',
					'identifier_2' => 'TransRefNumber',
					'amt' => 'Subtotal'
				),
				'static' => array(
					'CardAction' => '3',
					'PaymentType' => 'CC'
				)
			)					
		);

		return $map;
	}	
	
	/**
	 * Builds a request
	 * @param	array	array of params
	 * @param	string	the type of transaction
	 * @return	array	Array of transaction settings
	*/	
	protected function _build_request($params)
	{	
		$map = $this->method_map();
		$l = $this->_lib_method;

		$nodes = array();	
		$nodes[$map[$l]['api']] = array();
		$root = &$nodes[$map[$l]['api']];
		$root['test'] = 'haha';

		$root['StoreId'] = $this->_api_settings['store_id'];
		$root['Passphrase'] = $this->_api_settings['pass_phrase'];

		foreach($params as $k=>$v)
		{
			if(isset($map[$l]['keymatch'][$k]))
			{
				$key = $map[$l]['keymatch'][$k];

				if(strpos($key, ',') !== false) //If a key comprises multiple fields: complicated
				{
					$ex = explode(',', $key);
					
					if($k == 'cc_exp')
					{
						$k1 = $ex[0];
						$k2 = $ex[1];

						$root[$k1] = substr($v, 0, 2); //Sets the month
						$root[$k2] = substr($v, -2, 2); //Sets the year
					}
				}
				else //Simple = )
				{
					if(isset($nodes[$key]))
					{
						$root[$key] .= $v;
					}
					else
					{
						$root[$key] = $v;
					}
				}
			}
			else
			{
				error_log("$k is not a valid param for the $l method in the Psigate class");
			}
		}

		foreach($map[$l]['static'] as $k=>$v)
		{
			$root[$k] = $v;
		}

		$request = Payment_Request::build_xml_request(
			$this->_api_settings['xml_version'],
			$this->_api_settings['encoding'],
			$nodes
		);	

		return $request;	
	}

	/**
	 * Parse the response from the server
	 *
	 * @param	array
	 * @return	object
	 */		
	protected function _parse_response($xml)
	{	
		$details = (object) array();

		$as_array = Payment_Utility::arrayize_object($xml);

		$result = $as_array['Approved'];
		
		if(isset($as_array['OrderID']) && !empty($as_array['OrderID']))
		{
			$identifier = $as_array['OrderID'];
		}
		
		if(isset($as_array['TransRefNumber']))
		{
			$identifier2 = $as_array['TransRefNumber'];
		}
		
		$details->timestamp = $as_array['TransTime'];
		$details->gateway_response = $as_array;
		
		if(isset($identifier))
		{
			$identifier = (string) $identifier; 
			if(strlen($identifier) > 1)
			{
				$details->identifier = $identifier;
			}
		}
		
		if(isset($identifier2))
		{
			$identifier2 = (string) $identifier2; 
			if(strlen($identifier2) > 1)
			{		
				$details->identifier2 = $identifier2;
			}
		}
		
		if($result == 'APPROVED')
		{
			return Payment_Response::instance()->gateway_response(
				'Success',
				$this->_lib_method.'_success',
				$details
			);
		}
		
		if($result == 'ERROR' OR $result == 'DECLINED')
		{
			if(isset($as_array['ErrMsg']))
			{
				$message = $as_array['ErrMsg'];
				$message = explode(':', $message);
				$message = $message[1];
			}
			
			if(isset($message))
			{
				$details->reason = $message;
			}	

			return Payment_Response::instance()->gateway_response(
				'Failure',
				$this->_lib_method.'_gateway_failure',
				$details
			);				
		}
	}
}