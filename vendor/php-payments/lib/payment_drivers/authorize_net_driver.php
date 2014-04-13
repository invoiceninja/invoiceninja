<?php

class Authorize_Net_Driver extends Payment_Driver
{
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
	 *
	*/
	private $_lib_method;

	/**
	 * Stores Settings for the Transaction
	*/
	private $_settings;

	/**
	 * The Constructor Function
	*/
	public function __construct($config)
	{
		$this->_settings = array(
			'xml_version' => '1.0',
			'encoding' => 'utf-8',
			'xml_schema' => 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"',
			'email_customer' => (isset($config['email_customer'])) ? $config['email_customer'] : TRUE
		);

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
		$this->_lib_method = $method;
		$args = $params[0];

		$this->_endpoint = ($this->_settings['mode'] !== 'test') ? 'https://secure.authorize.net/gateway/transact.dll' : 'https://apitest.authorize.net/xml/v1/request.api';	

		$method_map = $this->method_map();
		
		$this->_api = $method_map[$method]['api'];
		$this->_api_method = (isset($method_map[$method]['method'])) ? $method_map[$method]['method'] : '';
		
		$request_string = $this->_build_request($args);

		$response_raw = Payment_Request::curl_request($this->_endpoint, $request_string);
		return $this->_parse_response($response_raw);
	}

	/**
	 * Maps PHP-Payments Methods to Details Particular to Each Request for that Method
	 */
	public function method_map()
	{
		$map = array(
			'oneoff_payment' => array(
				'api' => 'createTransactionRequest', 
				'method' => 'authCaptureTransaction',
				'required' => array(
					'cc_type',
					'cc_number',
					'cc_exp',
					'amt'
				)
			),
			'authorize_payment' => array(
				'api' => 'createTransactionRequest',
				'method' => 'authOnlyTransaction',
				'required' => array(
					'cc_type',
					'cc_number',
					'cc_exp',
					'amt'
				)
			),
			'capture_payment' => array(
				'api' => 'createTransactionRequest',
				'method' => 'priorAuthCaptureTransaction',
				'required' => array(
					'identifier'
				)
			),
			'void_payment' => array(
				'api' => 'createTransactionRequest',
				'method' => 'voidTransaction',
				'required' => array(
					'identifier'
				)
			),
			'get_transaction_details' => array(
				'api' => 'getTransactionDetailsRequest',
				'required' => array(
					'identifier'
				)
			),
			'refund_payment' => array(
				'api' => 'createTransactionRequest',
				'method' => 'refundTransaction',
				'required' => array(
					'identifier',
					'cc_number',
					'cc_exp',
					'amt'
				)
			),
			'recurring_payment' => array(
				'api' => 'ARBCreateSubscriptionRequest',
				'required' => array(
					'first_name',
					'last_name',
					'profile_start_date',
					'billing_period',
					'billing_frequency',
					'total_billing_cycles',
					'amt',
					'cc_type',
					'cc_exp',
					'cc_number',
					'country_code',
					'street',
					'city',
					'state',
					'postal_code'
				)
			),
			'get_recurring_profile' => array(
				'api' => 'ARBGetSubscriptionStatusRequest',
				'required' => array(
					'identifier'
				)
			),
			'update_recurring_profile' => array(
				'api' => 'ARBUpdateSubscriptionRequest',
				'required' => array(
					'identifier',
					'cc_number',
					'cc_exp'
				)
			),
			'cancel_recurring_profile' => array(
				'api' => 'ARBCancelSubscriptionRequest',
				'required' => array(
					'identifier'
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
		$nodes = array();
		$nodes['merchantAuthentication'] = array(
			'name' => $this->_settings['api_username'],
			'transactionKey' =>	$this->_settings['api_password'],		
		);	
		
		if($this->_api == 'createTransactionRequest')
		{		
			$nodes['transactionRequest'] = $this->_transaction_fields($params);						
			$nodes['transactionRequest']['transactionSettings'] = $this->_transaction_settings();		
		}

		if($this->_api == 'getTransactionDetailsRequest')
		{
			$nodes['transId'] = $params['identifier'];
		}

		if($this->_api == 'ARBGetSubscriptionStatusRequest' OR $this->_api == 'ARBUpdateSubscriptionRequest' OR $this->_api == 'ARBCancelSubscriptionRequest')
		{
			$nodes['subscriptionId'] = $params['identifier'];
		}	
		
		if($this->_api == 'ARBCreateSubscriptionRequest' OR $this->_api == 'ARBUpdateSubscriptionRequest')
		{
			$nodes['subscription'] = $this->_transaction_fields($params);
		}			
		
		$request_string = Payment_Request::build_xml_request(
			$this->_settings['xml_version'],
			$this->_settings['encoding'],
			$nodes,
			$this->_api,
			$this->_settings['xml_schema']
		);

		return $request_string;
	}


	/**
	 * Sets transaction settings
	 * @return	array	Array of transaction settings
	*/		
	protected function _transaction_settings()
	{
		return array(
			'repeated_key' => array(
				'name' => 'setting',
				'wraps'	=> FALSE,
				'values' => array(
					array(
						'settingName' => 'allowPartialAuth', 
						'settingValue'=> TRUE,
					),
					array(
						'settingName' => 'emailCustomer',
						'settingValue' => $this->_settings['email_customer']
					),
					array(
						'settingName' => 'recurringBilling',
						'settingValue' => FALSE
					),
					array(
						'settingName' => 'testRequest',
						'settingValue' => ($this->_settings['mode'] == 'test') ? true : false
					)
				)
			)
		);	
	}

	/**
	 * Sets fields to a request
	 * @param	string	The transaction type
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/		
	protected function _transaction_fields($params)
	{
		$fields = array();
		
		if(!is_null($this->_api_method))
		{
			$fields['transactionType'] = $this->_api_method;
		}
		
		if($this->_api == 'ARBCreateSubscriptionRequest')
		{

			$fields['name'] = $params['first_name'] . ' ' . $params['last_name'];

			if($params['billing_period'] != 'Month' && $params['billing_period'] != 'Day') 
			{
				return Payment_Response::instance()->return_response(
					'Failure', 
					'invalid_date_params',
					'local_response'
				);
			}
			
			if($params['billing_period'] == 'Month')
			{
				$params['billing_period'] = 'months';
			}
			
			if($params['billing_period'] == 'Day')
			{
				$params['billing_period'] = 'days';
			}
					
			$fields['paymentSchedule'] = array(
				'interval' => array(
					'length' => $params['billing_frequency'],
					'unit' => $params['billing_period'],
				),
				'startDate' => $params['profile_start_date'],
				'totalOccurrences' => $params['total_billing_cycles']
			);
			
			if(isset($params['trial_billing_cycles']) AND isset($params['trial_amt']))
			{
				$fields['paymentSchedule']['interval']['trialOccurrences'] = $params['trial_billing_cycles'];
				$fields['trialAmount'] = $params['trial_amt'];
			}		
		}
		
		if(isset($params['amt']))
		{
			$fields['amount'] = $params['amt'];
		}
		
		if(isset($params['cc_number']))
		{
			$fields['payment']['creditCard'] = $this->_add_payment('credit_card', $params);
		}
		
		if(isset($params['identifier']) AND $this->_api != 'ARBUpdateSubscriptionRequest')
		{
			$fields['refTransId'] = $params['identifier'];
		}
		
		$fields['order'] = $this->_build_order_fields($params);
		
		if(isset($params['tax_amt']))
		{
			$fields['tax'] = array(
				'amount' => $params['tax_amt']
			);
		}		
		
		if(isset($params['shipping']))
		{
			$fields['shipping'] = array(
				'amount' => $params['shipping_amt']
			);			
		}

		if(isset($params['po_num']))
		{
			$fields['poNumber'] = $params['po_num'];		
		}
		
		$fields['customer'] = $this->_build_customer_fields($params);
		
		$fields['billTo'] = $this->_build_bill_to_fields($params);

		$fields['shipTo'] = $this->_build_ship_to_fields($params);	

		if(isset($params['ip_address']))
		{
			$fields['customerIP'] = $params['ip_address'];
		}	
		
		return $fields;
	}

	/**
	 * Builds fields for order node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/	
	protected function _build_order_fields($params)
	{
		$order = array();
		
		if(isset($params['inv_num']))
		{
			if(isset($params['desc']))
			{
				$order = array(
					'invoiceNumber' => $params['inv_num'],
					'description' => $params['desc']
				);				
			}
			else
			{
				$order = array(
					'invoiceNumber' => $params['inv_num']				
				);
			}
		}
		
		return $order;	
	}

	/**
	 * Builds fields for billTo node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/			
	protected function _build_bill_to_fields($params)
	{
		$bill_to = array();
		
		if(isset($params['first_name']))
		{
			$bill_to['firstName'] = $params['first_name'];
		}	
		
		if(isset($params['last_name']))
		{
			$bill_to['lastName'] = $params['last_name'];
		}

		if(isset($params['business_name']))
		{
			$bill_to['company'] = $params['business_name'];
		}

		if(isset($params['street']))
		{
			$bill_to['address'] = $params['street'];
		}

		if(isset($params['city']))
		{
			$bill_to['city'] = $params['city'];
		}

		if(isset($params['state']))
		{
			$bill_to['state'] = $params['state'];
		}

		if(isset($params['postal_code']))
		{
			$bill_to['zip'] = $params['postal_code'];
		}

		if(isset($params['country']))
		{
			$bill_to['country'] = $params['country'];
		}

		if(isset($params['phone']))
		{
			$bill_to['phoneNumber'] = $params['phone'];
		}	

		if(isset($params['fax']))
		{
			$bill_to['faxNumber'] = $params['fax'];
		}
		
		return $bill_to;		
	}

	/**
	 * Builds fields for customer node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/		
	protected function _build_customer_fields($params)
	{
		$customer = array();
		
		if(isset($params['email']))
		{	
			$customer['email'] = $params['email'];
		}

		if(isset($params['phone']))
		{	
			$customer['phoneNumber'] = $params['phone'];
		}

		if(isset($params['fax']))
		{	
			$customer['faxNumber'] = $params['fax'];
		}		
				
	}

	/**
	 * Builds fields for shipTo node
	 * @param	array	Array of params
	 * @return	array	Array of fields
	*/		
	protected function _build_ship_to_fields($params)
	{
		$ship_to = array();
		
		if(isset($params['ship_to_first_name']))
		{
			$ship_to['firstName'] = $params['ship_to_first_name'];
		}

		if(isset($params['ship_to_last_name']))
		{
			$ship_to['lastName'] = $params['ship_to_last_name'];
		}	

		if(isset($params['ship_to_company']))
		{
			$ship_to['company'] = $params['ship_to_company'];
		}	

		if(isset($params['ship_to_street']))
		{
			$ship_to['address'] = $params['ship_to_street'];
		}	

		if(isset($params['ship_to_city']))
		{
			$ship_to['city'] = $params['ship_to_city'];
		}	

		if(isset($params['ship_to_state']))
		{
			$ship_to['state'] = $params['ship_to_state'];
		}	

		if(isset($params['ship_to_postal_code']))
		{
			$ship_to['zip'] = $params['ship_to_postal_code'];
		}	

		if(isset($params['ship_to_country']))
		{
			$ship_to['country'] = $params['ship_to_country'];
		}	
		
		return $ship_to;	
	}

	/**
	 * Add a payment method to a request
	 * @param	string	Bank or credit card #
	 * @param	array	params
	 * @return	array	array
	*/			
	protected function _add_payment($type, $params)
	{	
		if($type === 'credit_card')
		{
			$card = array();
			
			if(isset($params['cc_number']))
			{
				$card['cardNumber'] = $params['cc_number'];
			}
			
			if(isset($params['cc_exp']))
			{
				$card['expirationDate'] = $params['cc_exp'];
			}
			
			if(isset($params['cc_code']))
			{
				$card['cardCode'] = $params['cc_code'];
			}
			
			return $card;
		}
	}	

	/**
	 * Parse the response from the server
	 *
	 * @param	array
	 * @return	object 
	 */		
	protected function _parse_response($xml)
	{
		//If it failed when being parsed as XML, go ahead and return it
		if(isset($xml->status) && $xml->status == 'failure') return $xml;

		$details = (object) array();

		$as_array = Payment_Utility::arrayize_object($xml);
	
		$result = $as_array['messages']['resultCode'];
		
		if(isset($as_array['transactionResponse']))
		{
			$identifier = $as_array['transactionResponse']['transId'];
		}
		
		if(isset($as_array['subscriptionId']))
		{
			$identifier = $as_array['subscriptionId'];
		}
		
		$timestamp = gmdate('c');
		$details->timestamp = $timestamp;
		$details->gateway_response = $as_array;
		
		if(isset($identifier) AND strlen($identifier) > 1)
		{
			$details->identifier = $identifier;
		}
		
		if($result == 'Ok')
		{
			return Payment_Response::instance()->gateway_response(
				'Success',
				$this->_lib_method.'_success',
				$details
			);
		}
		
		if($result == 'Error')
		{
			if(isset($as_array['transactionResponse']['errors']['error']['errorText']))
			{
				$message = $as_array['transactionResponse']['errors']['error']['errorText'];
			}
			
			if(isset($as_array['messages']['message']['text']))
			{
				$message = $as_array['messages']['message']['text'];
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