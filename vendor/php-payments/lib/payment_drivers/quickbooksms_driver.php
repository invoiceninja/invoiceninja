<?php

class QuickBooksMS_Driver extends Payment_Driver
{
	/**
	 * The PHP-Payments method
	*/
	private $_lib_method;

	/**
	 * The API method currently being utilized
	*/
	private $_api_method;		

	/**
	 * The API method currently being utilized
	*/
	private $_api_endpoint;	

	/**
	 * The endpoint to use for test requests
	*/
	private $_api_endpoint_test = 'https://merchantaccount.ptc.quickbooks.com/j/AppGateway';

	/**
	 * The endpoint to use for production requests
	*/
	private $_api_endpoint_production = 'https://merchantaccount.quickbooks.com/j/AppGateway';

	/**
	 * Version
	*/
	private $_version = '4.5';

	/**
	 * An array for storing all settings
	*/	
	private $_settings = array();

	/**
	 * An array for storing all request data
	*/	
	private $_request = array();
	
	/**
	 * Constructor method
	*/		
	public function __construct($config)
	{
		$this->_api_endpoint = ($config['mode'] == 'test') ? $this->_api_endpoint_test : $this->_api_endpoint_production;

		$this->_api_settings = array(
			'login'			=> $config['api_application_login'],
			'connection_ticket'	=> $config['api_connection_ticket'],
			'xml_version'	=> '1.0',
			'encoding'		=> 'utf-8',
			'xml_extra'		=> 'qbmsxml version="'.$this->_version.'"'
		);
	}

	/**
	 * Call Magic Method
	 */
	public function __call($method, $params)
	{
		$this->_lib_method = $method;
		$args = $params[0];

		try {
			$request = $this->_build_request($args);
		}
		catch(Exception $e){
			return Payment_Response::instance()->gateway_response(
				'failure',
				$e->getMessage(),
				''
			);
		}

		$response_raw = Payment_Request::curl_request($this->_api_endpoint, $request, "application/x-qbmsxml");	
		return $this->_parse_response($response_raw);
	}

	/**
	 * The Method Map
	*/
	public function method_map()
	{
		$map = array(
			'oneoff_payment' => array(
				'api' => 'CustomerCreditCardChargeRq',
				'required' => array(
					'cc_number',
					'cc_exp',
					'amt'
				),
				'keymatch' => array(
					'amt' => 'Amount',
					'cc_number' => 'CreditCardNumber',
					'cc_exp' => 'ExpirationMonth,ExpirationYear',
					'first_name' => 'NameOnCard',
					'last_name' => 'NameOnCard',
					'street' => 'CreditCardAddress',
					'postal_code' => 'CreditCardPostalCode',
					'tax_amt' => 'SalesTaxAmt',
					'cc_code' => 'CardSecurityCode'				
				)
			),
			'authorize_payment' => array(
				'api' => 'CustomerCreditCardAuthRq',
				'required' => array(
					'cc_number',
					'cc_exp',
					'amt'
				),
				'keymatch' => array(
					'amt' => 'Amount',
					'cc_number' => 'CreditCardNumber',
					'cc_exp' => 'ExpirationMonth,ExpirationYear',
					'first_name' => 'NameOnCard',
					'last_name' => 'NameOnCard',
					'street' => 'CreditCardAddress',
					'postal_code' => 'CreditCardPostalCode',
					'tax_amt' => 'SalesTaxAmt',
					'cc_code' => 'CardSecurityCode'
				),
				'static' => array(
					'isCardPresent' => '0'
				)
			),
			'capture_payment' => array(
				'api' => 'CustomerCreditCardCaptureRq',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'CreditCardTransID'
				)
			),
			'void_payment' => array(
				'api' => 'CustomerCreditCardTxnVoidRq',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'CreditCardTransID'
				)
			),
			'refund_payment' => array(
				'api' => 'CustomerCreditCardTxnVoidOrRefundRq',
				'required' => array(
					'identifier',
					'amt'
				),
				'keymatch' => array(
					'identifier' => 'CreditCardTransID',
					'amt' => 'Amount',
				)
			),
			'recurring_payment' => array(
				'api' => 'CustomerCreditCardChargeRq',
				'required' => array(
					'cc_number',
					'cc_exp',
					'amt'
				),
				'keymatch' => array(
					'amt' => 'Amount',
					'cc_number' => 'CreditCardNumber',
					'cc_exp' => 'ExpirationMonth,ExpirationYear',
					'first_name' => 'NameOnCard',
					'last_name' => 'NameOnCard',
					'street' => 'CreditCardAddress',
					'postal_code' => 'CreditCardPostalCode',
					'tax_amt' => 'SalesTaxAmt',
					'cc_code' => 'CardSecurityCode'
				),
				'static' => array(
					'isRecurring' => '1'
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
	protected function _build_request($params)
	{
		$session = $this->_get_session_ticket();

		$map = $this->method_map();
		$l = $this->_lib_method;
		
		$nodes = array();

		$nodes['SignonMsgsRq'] = array(
			'SignonTicketRq' => array(
				'ClientDateTime' => $session->time,
				'SessionTicket' => $session->ticket
			)
		);

		$unordered = array();
		$unordered['TransRequestID'] = mt_rand(1, 1000000); //This is used to avoid duplicate transactions coming from the merchant.
		
		foreach($params as $k=>$v)
		{
			if(isset($map[$l]['keymatch'][$k]))
			{
				$key = $map[$l]['keymatch'][$k];
				if(strpos($key, ',') !== false)
				{
					$ex = explode(',', $key);

					if($k == 'cc_exp')
					{
						$month = substr($params['cc_exp'], 0, 2);
						$year = substr($params['cc_exp'], -4, 4);

						$unordered[$ex[0]] = $month;
						$unordered[$ex[1]] = $year;
					}
				}
				else
				{
					if(isset($root[$key]))
					{
						$unordered[$key] .= $v;
					}
					else
					{
						$unordered[$key] = $v;
					}
				}
			}
			else
			{
				error_log("$k is not a valid param for the $l method of QuickBooksMS");
			}
		}

		if(isset($map[$l]['static']))
		{
			$static = $map[$l]['static'];
			foreach($static as $k=>$v)
			{
				$unordered[$k] = $v;
			}
		}

		//QuickBooks requires a specific sort order...
		$order = array('TransRequestID', 'CreditCardNumber', 'ExpirationMonth', 'ExpirationYear', 'isCardPresent', 'isRecurring', 'Amount', 'NameOnCard', 'CreditCardAddress', 'CreditCardCity', 'CreditCardState', 'CreditCardPostalCode', 'CommercialCode', 'SalesTaxAmount', 'CardSecurityCode', 'Lodging', 'ClientTransID', 'InvoiceID', 'Comment');
		$ordered = Payment_Utility::sort_array_by_array($unordered, $order);

		$nodes['QBMSXMLMsgsRq'] = array(
			$map[$l]['api'] => $ordered
		);

		$request = Payment_Request::build_xml_request(
			$this->_api_settings['xml_version'],
			$this->_api_settings['encoding'],
			$nodes,					
			'QBMSXML',
			null,
			$this->_api_settings['xml_extra']
		);
	
		return $request;	
	}

	/**
	 * Get the Session Ticket So We Can Create Transactions
	 *
	 * @return	object	$session->time, $session->ticket
	*/
	private function _get_session_ticket()
	{
		$nodes = array();

		$nodes['SignonMsgsRq'] = array(
			'SignonDesktopRq' => array(
				'ClientDateTime' => gmdate('c'),
				'ApplicationLogin' => $this->_api_settings['login'],
				'ConnectionTicket' => $this->_api_settings['connection_ticket']
			)
		);

		$request = Payment_Request::build_xml_request(
			$this->_api_settings['xml_version'],
			$this->_api_settings['encoding'],
			$nodes,					
			'QBMSXML',
			null,
			$this->_api_settings['xml_extra']
		);

		$response_raw = Payment_Request::curl_request($this->_api_endpoint, $request, "application/x-qbmsxml");	

		if(isset($response_raw->SignonMsgsRs->SignonDesktopRs))
		{
			$r = $response_raw->SignonMsgsRs->SignonDesktopRs;
			$session = (object) array(
				'time' => $r->ServerDateTime,
				'ticket' => $r->SessionTicket
			);

			return $session;
		}
		else
		{
			throw new Exception('authentication_failure');
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
		$details = (object) array();

		$as_array = Payment_Utility::arrayize_object($xml);
		
		$signon = (isset($as_array['SignonMsgsRs'])) ? $as_array['SignonMsgsRs'] : '';
		$response = (isset($as_array['QBMSXMLMsgsRs'])) ? $as_array['QBMSXMLMsgsRs'] : '';
		$result = '';
		$message = '';
		$identifier = '';
		
		if(isset($response['CustomerCreditCardChargeRs']))
		{
			$result = $response['CustomerCreditCardChargeRs']['@attributes']['statusCode'];
			$message = $response['CustomerCreditCardChargeRs']['@attributes']['statusMessage'];	
			$identifier = $response['CustomerCreditCardChargeRs']['CreditCardTransID'];	
		}

		if(isset($response['CustomerCreditCardAuthRs']))
		{
			$result = $response['CustomerCreditCardAuthRs']['@attributes']['statusCode'];
			$message = $response['CustomerCreditCardAuthRs']['@attributes']['statusMessage'];	
			$identifier = $response['CustomerCreditCardAuthRs']['CreditCardTransID'];	
		}
	
		if(isset($response['CustomerCreditCardCaptureRs']))
		{
			$result = $response['CustomerCreditCardCaptureRs']['@attributes']['statusCode'];
			$message = $response['CustomerCreditCardCaptureRs']['@attributes']['statusMessage'];	
			$identifier = $response['CustomerCreditCardCaptureRs']['CreditCardTransID'];	
		}
		
		if(isset($response['CustomerCreditCardTxnVoidRs']))
		{
			$result = $response['CustomerCreditCardTxnVoidRs']['@attributes']['statusCode'];
			$message = $response['CustomerCreditCardTxnVoidRs']['@attributes']['statusMessage'];	
			$identifier = $response['CustomerCreditCardTxnVoidRs']['CreditCardTransID'];		
		}
		
		if(isset($response['CustomerCreditCardTxnVoidOrRefundRs']))
		{
			$result = $response['CustomerCreditCardTxnVoidOrRefundRs']['@attributes']['statusCode'];
			$message = $response['CustomerCreditCardTxnVoidOrRefundRs']['@attributes']['statusMessage'];
			if(isset($response['CustomerCreditCardTxnVoidOrRefundRs']['CreditCardTransID']))
			{	
				$identifier = $response['CustomerCreditCardTxnVoidOrRefundRs']['CreditCardTransID'];		
			}
		}		
			
		$details->gateway_response = $as_array;
		
		if($result === '0')
		{ //Transaction was successful
			$details->identifier = $identifier;
			
			$details->timestamp = (isset($signon['ServerDateTime'])) ? $signon['ServerDateTime'] : '';
			
			return Payment_Response::instance()->gateway_response(
				'Success',
				$this->_lib_method.'_success',
				$details
			);			
		}
		else
		{ //Transaction failed
			$details->reason = $message;

			return Payment_Response::instance()->gateway_response(
				'Failure',
				$this->_lib_method.'_gateway_failure',
				$details
			);				
		}
	}
		
}