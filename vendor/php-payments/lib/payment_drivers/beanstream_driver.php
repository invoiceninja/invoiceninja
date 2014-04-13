<?php

class Beanstream_Driver
{	
	/**
	 * The API method currently being utilized
	*/
	protected $_api_method;		

	/**
	 * The API method currently being utilized
	*/
	private $_api_endpoint = 'https://www.beanstream.com/scripts/process_transaction.asp?';	

	/**
     * Recurring Endpoint
	*/
	private $_recurring_endpoint = 'https://www.beanstream.com/scripts/recurring_billing.asp?';

	/**
	 * An array for storing all settings
	*/	
	private $_settings = array();
	
	/**
	 * The final string to be sent in the http query
	*/	
	private $_http_query;	

	/**
	 * Constructor method
	*/		
	public function __construct($config)
	{
		$this->_api_settings = array(
			'merchant_id'	=> $config['merchant_id'],
			'username'		=> $config['username'],
			'password'		=> $config['password'],
			'requestType'	=> 'BACKEND'
		);

		$this->config = $config;
	}

	private function _recurring_settings()
	{
		$this->_api_endpoint = $this->_recurring_endpoint;
		$this->_api_settings['passCode'] = $this->config['passCode'];
		$this->_api_settings['merchantId'] = $this->config['merchant_id'];
		$this->_api_settings['serviceVersion'] = "1.0";
		$this->_api_settings = array_reverse($this->_api_settings);
		unset($this->_api_settings['username']);
		unset($this->_api_settings['password']);
		unset($this->_api_settings['requestType']);
		unset($this->_api_settings['merchant_id']);
	}
	
	/**
 	 * Magic Method for Making Method Calls
	*/
	public function __call($method, $params)
	{
		$this->_lib_method = $method;
		$args = $params[0];
		$method_map = $this->method_map();

		$this->_api_method = array($method_map[$method]['descriptor'] => $method_map[$method]['api']);
		$request = (isset($method_map[$method]['method'])) ? $this->_build_request($args, $method_map[$method]['method']) : $this->_build_request($args);

		return $this->_handle_query($method, $request);
	}

	/**
	 * Build requests
	 * @param	array	An array of payment param
	 * @return	void
	*/	
	private function _build_request($params, $transaction_type = NULL)
	{	
		$request = $this->_build_common_fields($params);
		
		//If it's a recurring transaction, but not profile creation
		if(strstr($transaction_type, 'recurring') !== FALSE AND $transaction_type !== 'recurring')
		{
			$this->_recurring_settings();
		}
		
		if($transaction_type === 'recurring' OR $transaction_type === 'recurring_modify')
		{
			$request['trnRecurring'] = '1';
		
			if(isset($params['billing_period']) AND !empty($params['billing_period']))
			{
				$period = strtolower($params['billing_period']);
				$periods = array(
					'month'	=>	'M',
					'year'	=>	'Y',
					'week'	=>	'W',
					'day'	=>	'D'
				);
				$request['rbBillingPeriod'] = $periods[$period];	
			}
			
			if(isset($params['billing_frequency']))
			{
				$request['rbBillingIncrement'] = $params['billing_frequency'];
			}
			
			$request['rbCharge'] = $this->config['delay_charge'];
			$request['processBackPayments'] = $this->config['bill_outstanding'];
			
			if(isset($params['profile_start_date']))
			{
				$start = $params['profile_start_date'];
				$m = substr($start, 4, 2);
				$d = substr($start, -2, 2);
				$y = substr($start, 0, 4);
				
				$first_bill = $m.$d.$y;
				
				$request['rbFirstBilling'] = $first_bill;
			}
			
			//rbSecondBilling could be integrated as well.  It is a field used in combination with rbFirstBilling to prorate a first payment. The second billing date will mark the start of the regular billing schedule. The first customer payment will be prorated based on the difference between the first and second billing date. All subsequent billing intervals will be counted after this date.	This value must be formatted as MMDDYYYY.
			
			//Profile end date could also be passed here as rbExpiry
			
			//Did not use rbApplyTax1 or rbApplyTax2
		}	
		
		if($transaction_type === 'recurring_modify' OR $transaction_type === 'recurring_cancel' OR $transaction_type === 'recurring_suspend' OR $transaction_type === 'recurring_activate')
		{
			$request['rbAccountID'] = $params['identifier'];
			$request['processBackPayments'] = $this->config['bill_outstanding'];
		}
		
		if($transaction_type === 'recurring_suspend')
		{
			$request['rbBillingState'] = 'O';
		}
		
		if($transaction_type === 'recurring_activate')
		{
			$request['rbBillingState'] = 'A';
		}
		
		return $request;
	}

	/**
	 * Build common fields for the request
	 * @param	array	An array of payment param
	 * @return	void
	*/		
	private function _build_common_fields($params)
	{
		$request = array();
		
		if(isset($this->_api_method['trnType']))
		{
			$method = $this->_api_method['trnType'];
		}
		
		if(isset($this->_api_method['operationType']))
		{
			$method = $this->_api_method['operationType'];
		}
			
		if(isset($params['first_name']) AND isset($params['last_name']))
		{
			$name = $params['first_name'].' '.$params['last_name'];
			$request['ordName'] = $name;
			$request['trnCardOwner'] = $name;
		}
		
		if(isset($params['cc_exp']))
		{
			$month = substr($params['cc_exp'], 0, 2);
			$year = substr($params['cc_exp'], -2, 2);
			$request['trnExpMonth'] = $month;
			$request['trnExpYear'] = $year;
		}

		if(isset($params['cc_code']))
		{
			$request['trnCardCvd'] = $params['cc_code'];
		}

		if(isset($params['identifier']))
		{
			if($method === 'PAC' OR $method === 'VP' OR $method === 'VR' OR $method === 'R')
			{
				$request['adjId'] = $params['identifier'];
			}

			if($method === 'Q' OR $method === 'P')
			{
				$request['trnOrderNumber'] = $params['identifier'];
			}
		}
		
		if(isset($params['cc_number']))
		{
			$request['trnCardNumber'] = $params['cc_number'];
		}
		
    if (isset($params['amt']))
    {
        if ($method == 'M')
        {
            $request['Amount'] = $params['amt'];
        } else {
            $request['trnAmount'] = $params['amt'];
        }
    }
		
		if(isset($params['phone']))
		{
			$request['ordPhoneNumber'] = $params['phone'];
		}
		
		if(isset($params['email']))
		{
			$request['ordEmailAddress'] = $params['email'];
		}
		
		if(isset($params['street']))
		{
			$request['ordAddress1'] = $params['street'];
		}
		
		if(isset($params['city']))
		{
			$request['ordCity'] = $params['city'];
		}
		
		if(isset($params['state']))
		{
			$request['ordProvince'] = $params['state'];
		}
		
		if(isset($params['postal_code']))
		{
			$request['ordPostalCode'] = $params['postal_code'];
		}
		
		if(isset($params['country']))
		{
			$request['ordCountry'] = $params['country'];
		}
		
		if(isset($params['ship_to_name']))
		{
			$request['shipName'] = $params['ship_to_name'];
		}
		
		if(isset($params['ship_to_phone_number']))
		{
			$request['shipPhoneNumber'];
		}
		
		if(isset($params['ship_to_street']))
		{
			$request['shipAddress1'] = $params['ship_to_street'];
		}
		
		if(isset($params['ship_to_city']))
		{
			$request['shipCity'] = $params['ship_to_city'];
		}
		
		if(isset($params['ship_to_state']))
		{
			$request['shipProvince'] = $params['ship_to_state'];
		}
		
		if(isset($params['ship_to_postal_code']))
		{
			$request['shipPostalCode'] = $params['ship_to_postal_code'];
		}
		
		if(isset($params['ship_to_country']))
		{
			$request['shipCountry'] = $params['ship_to_country'];
		}
		
		if(isset($params['note']))
		{
			$request['trnComments'] = $params['note'];
		}
		
		if(isset($params['ip_address']))
		{
			$request['customerIP'] = $params['ip_address'];
		}	

		return $request;
	}	

	/**
	 * Maps PHP Payments Methods to Beanstream Methods
	*/
	public function method_map()
	{
		$map = array(
			'oneoff_payment' => array(
				'api' => 'P',
				'descriptor' => 'trnType',
				'required' => array(
  			        'cc_number',
				  	'cc_exp',
				 	'cc_code',
					'amt',
					'first_name',
				    'last_name',
					'phone',
					'email',
					'street',
					'city',
					'state',
	       	 		'country',
    			    'postal_code'			
				)
			),
			'authorize_payment' => array(
				'api' => 'PA',
				'descriptor' => 'trnType',
				'required' => array(
					'cc_number',
					'cc_exp',
					'cc_code',
					'amt',
					'first_name',
					'last_name',
					'phone',
					'email',
					'street',
					'city',
					'state',
					'country',
					'postal_code'
				)
			),
			'capture_payment' => array(
				'api' => 'PAC',
				'descriptor' => 'trnType',
				'required' => array(
					'identifier',
					'amt'
				)
			),
			'void_payment' => array(
				'api' => 'VP',
				'descriptor' => 'trnType',
				'required' => array(
					'identifier',
					'amt'
				)		
			),
			'void_refund' => array(
				'api' => 'VR',
				'descriptor' => 'trnType',
				'required' => array(
					'identifier',
					'amt',
					'first_name',
					'last_name',
					'cc_number',
					'cc_exp',
					'email',
					'phone',
					'street',
					'city',
					'state',
					'country'
				)
			),
			'refund_payment' => array(
				'api' => 'R',
				'descriptor' => 'trnType',
				'required' => array(
					'identifier'
				)
			),
			'get_transaction_details' => array(
				'api' => 'Q',
				'descriptor' => 'trnType',
				'required' => array(
					'identifier'
				)
			),
			'recurring_payment' => array(
				'api' => 'P',
				'descriptor' => 'trnType',
				'method' => 'recurring',
				'required' => array(
					'cc_number',
					'cc_exp',
					'cc_code',
					'amt',
					'first_name',
					'last_name',
					'phone',
					'email',
					'street',
					'city',
					'state',
					'country',
					'postal_code'
				)
			),
			'cancel_recurring_profile' => array(
				'api' => 'C',
				'descriptor' => 'operationType',
				'method' => 'recurring_cancel',
				'required' => array(
					'identifier'
				)
			),
			'suspend_recurring_profile' => array(
				'api' => 'M',
				'descriptor' => 'operationType',
				'method' => 'recurring_suspend',
				'required' => array(
					'identifier'
				)
			),
			'activate_recurring_profile' => array(
				'api' => 'M',
				'descriptor' => 'operationType',
				'method' => 'recurring_activate',
				'required' => array(
					'identifier'
				)
			),
			'update_recurring_profile' => array(
				'api' => 'M',
				'descriptor' => 'operationType',
				'method' => 'recurring_modify',
				'required' => array(
					'identifier'
				)
			)
		);

		return $map;
	}	
	
	/**
	 * Build the query for the response and call the request function
	 *
	 * @param	array
	 * @param	array
	 * @return	array
	 */		
	private function _handle_query($method, $request)
	{
		$settings = array_merge($this->_api_settings, $this->_api_method);
		$merged = array_merge($settings, $request);

		$request = http_build_query($merged);
		$this->_http_query = $this->_api_endpoint.$request;
		
		$request = Payment_Request::curl_request($this->_http_query);	
		
		$response = $this->_parse_response($request);
		
		return $response;
	}

	/**
	 * Parse the response from the server
	 *
	 * @param	array
	 * @return	object
	 */		
	private function _parse_response($response)
	{	
		$details = (object) array();
        if (is_object($response))
        {
            if ($response->code == '1')
            {
                return Payment_Response::instance()->gateway_response(
                    'Success',
                    $this->_lib_method . '_success',
                    $details
                );
            }
            else
            {
                $details->reason = $response->message;
                return Payment_Response::instance()->gateway_response(
                    'Failure',
                    $this->_lib_method . '_gateway_failure',
                    $details
                );
            }
        }
        elseif(strstr($response, '<response>'))
		{
			$response = Payment_Utility::parse_xml($response);
			$response = Payment_Utility::arrayize_object($response);
			$details->gateway_response = $response;
							
			if($response['code'] == '1')
			{
				return Payment_Response::instance()->gateway_response(
					'Success',
					$this->_lib_method.'_success',
					$details
				);			
			}
			else
			{
				$details->reason = $response['message'];
				return Payment_Response::instance()->gateway_response(
					'Failure',
					$this->_lib_method.'_gateway_failure',
					$details
				);				
			}
		}
		else
		{
			$results = explode('&',urldecode($response));
			foreach($results as $result)
			{
				list($key, $value) = explode('=', $result);
				$gateway_response[$key]=$value;
			}
			
			$details->gateway_response = $gateway_response;	
			$details->timestamp = (isset($gateway_response['trnDate'])) ? $gateway_response['trnDate'] : gmdate('c');		
				
			if(isset($gateway_response['trnApproved']) && $gateway_response['trnApproved'] == '1')
			{	
				$details->identifier = (isset($gateway_response['trnId'])) ? $gateway_response['trnId'] : null;
				
				if(isset($gateway_response['rbAccountId']))
				{
					$details->identifier = $gateway_response['rbAccountId'];
				}
				
				return Payment_Response::instance()->gateway_response(
					'success',
					$this->_lib_method.'_success',
					$details
				);
			}
			else
			{
				$details->reason = (isset($gateway_response['messageText'])) ? $gateway_response['messageText'] : null;
				
				return Payment_Response::instance()->gateway_response(
					'failure',
					$this->_lib_method.'_gateway_failure',
					$details
				);		
			}	
		}
	}
}