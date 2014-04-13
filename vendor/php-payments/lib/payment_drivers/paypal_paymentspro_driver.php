<?php

class PayPal_PaymentsPro_Driver extends Payment_Driver
{	
	/**
	 * The PHP Payments Method
	*/
	private $_lib_method;

	/**
	 * The Paypal API to use
	*/
	private $_api;

	/**
	 * Test or Production Mode
	*/
	private $_mode;

	/**
	 * The API endpoint to send the request to
	*/
	private $_api_endpoint = 'https://api-3t.paypal.com/nvp';

	/**
	 * The API endpoint to send a test request to
	*/
	private $_api_endpoint_test = 'https://api-3t.sandbox.paypal.com/nvp';

	/**
	 * Current Version of PayPal's API
	*/
	private $_api_version = '66.0';

/**
	 * An array for storing all settings
	*/	
	private $_settings = array();

	/**
	 * Constructor method
	*/		
	public function __construct($config)
	{	
		$this->_settings = array(
			'USER'	=> $config['api_username'],
			'PWD'	=> $config['api_password'],
			'SIGNATURE'	=> $config['api_signature'],
			'VERSION' => $this->_api_version
		);
		$this->_mode = $config['mode'];
	}

	/**
	 * Caller Magic Method
	*/
	public function __call($method, $params)
	{
		$this->_lib_method = $method;
	
		$args = $params[0];
		$request = $this->_build_request($args);
		$endpoint = ($this->_mode == 'production') ? $this->_api_endpoint : $this->_api_endpoint_test;
		$request_string = $endpoint.'?'.$request;

		$raw_response = Payment_Request::curl_request($request_string);
		return $this->_parse_response($raw_response);
	}

	/**
	 * Maps Methods to Keys
	*/ 
	public function method_map()
	{
		$map = array(
			'oneoff_payment' => array(
				'api' => 'DoDirectPayment',
				'required' => array(
					'cc_type',
					'cc_number',
					'cc_exp',
					'amt'
				),
				'keymatch' => array(
					'ip_address'	=>	'IPADDRESS',
					'cc_type'		=>	'CREDITCARDTYPE',
					'cc_number'		=>	'ACCT',
					'cc_exp'		=>	'EXPDATE',
					'cc_code'		=>	'CVV2',
					'email'			=>	'EMAIL',
					'first_name'	=>	'FIRSTNAME',
					'last_name'		=>	'LASTNAME',
					'street'		=>  'STREET',
					'street2'		=>	'STREET2',
					'city'			=>	'CITY',
					'state'			=>	'STATE',
					'country'		=>	'COUNTRY',
					'postal_code'	=>	'ZIP',
					'amt'			=>	'AMT',
					'phone'			=>	'SHIPTOPHONENUM',
					'currency_code'	=>	'CURRENCYCODE',
					'item_amt'		=>	'ITEMAMT',
					'shipping_amt'	=>	'SHIPPINGAMT',
					'insurance_amt'	=>	'INSURANCEAMT',
					'shipping_disc_amt'	=>	'SHIPDISCAMT',
					'handling_amt'	=>	'HANDLINGAMT',
					'tax_amt'		=>	'TAXAMT',
					'desc'			=>	'DESC',
					'custom'		=>	'CUSTOM',
					'inv_num'		=>	'INVNUM',
					'notify_url'	=>	'NOTIFYURL',
					'ship_to_first_name'	=>	'SHIPTONAME',
					'ship_to_last_name'	=> 'SHIPTONAME',
					'ship_to_street'=>	'SHIPTOSTREET',
					'ship_to_city'	=>	'SHIPTOCITY',
					'ship_to_state'	=>	'SHIPTOSTATE',
					'ship_to_postal_code'	=>	'SHIPTOZIP',
					'ship_to_country'	=>	'SHIPTOCOUNTRY',
				),
				'static' => array(
					'PAYMENTACTION'	=>	'Sale'
				)
			),
			'reference_payment'	=>	array(
				'api' => 'DoReferenceTransaction',
				'required' => array(
					'identifier',  //Reference for a previous payment
					'amt'
				),
				'keymatch' => array(
					'identifier' => 'REFERENCEID',
					'amt' => 'AMT'
				),
				'static' => array(
					'PAYMENTACTION'	=>	'Sale'
				)
			),
			'authorize_payment'	=>	array(
				'api'	=>	'DoDirectPayment',
				'required' => array(
					'cc_type',
					'cc_number',
					'cc_exp',
					'amt'
				),
				'keymatch'	=>	array(
					'ip_address'	=>	'IPADDRESS',
					'cc_type'		=>	'CREDITCARDTYPE',
					'cc_number'		=>	'ACCT',
					'cc_exp'		=>	'EXPDATE',
					'cc_code'		=>	'CVV2',
					'email'			=>	'EMAIL',
					'first_name'	=>	'FIRSTNAME',
					'last_name'		=>	'LASTNAME',
					'street'		=>  'STREET',
					'street2'		=>	'STREET2',
					'city'			=>	'CITY',
					'state'			=>	'STATE',
					'country'		=>	'COUNTRY',
					'postal_code'	=>	'ZIP',
					'amt'			=>	'AMT',
					'phone'			=>	'SHIPTOPHONENUM',
					'currency_code'	=>	'CURRENCYCODE',
					'item_amt'		=>	'ITEMAMT',
					'shipping_amt'	=>	'SHIPPINGAMT',
					'insurance_amt'	=>	'INSURANCEAMT',
					'shipping_disc_amt'	=>	'SHIPDISCAMT',
					'handling_amt'	=>	'HANDLINGAMT',
					'tax_amt'		=>	'TAXAMT',
					'desc'			=>	'DESC',
					'custom'		=>	'CUSTOM',
					'inv_num'		=>	'INVNUM',
					'notify_url'	=>	'NOTIFYURL',
				),
				'static' => array(
					'PAYMENTACTION'	=>	'Authorization'
				)
			),
			'capture_payment'	=>	array(
				'api'	=>	'DoDirectPayment',
				'required' => array(
					'identifier',
					'amt',
					'cc_type',
					'cc_number',
					'cc_exp'
				),
				'keymatch' => array(
					'identifier'	=>	'AUTHORIZATIONID',
					'amt'			=>	'AMT',
					'cc_type'		=>	'CREDITCARDTYPE',
					'cc_number'		=>	'ACCT',
					'cc_exp'		=>	'EXPDATE',
					'inv_num'		=>	'INVOICEID',
					'note'			=>	'NOTE',
					'desc'			=>	'SOFTDESCRIPTOR' //Description for credit card statement
				)
			),
			'void_payment'	=>	array(
				'api'	=>	'DoVoid',
				'required'	=>	array(
					'identifier',
					'note'
				),
				'keymatch' => array(
					'identifier'	=>	'AUTHORIZATIONID',
					'note'			=>	'NOTE'
				)
			),
			'change_transaction_status'	=>	array(
				'api'	=>	'ManagePendingTransactionStatus',
				'required'	=>	array(
					'identifier',
					'action'
				),
				'keymatch' => array(
					'identifier'	=>	'TRANSACTIONID',
					'action'		=>	'ACTION'
				)
			),
			'refund_payment' =>	array(
				'api'	=>	'RefundTransaction',
				'required'	=>	array(
					'identifier'
				),
				'keymatch'	=>	array(
					'identifier'	=>	'TRANSACTIONID',
					'refund_type'	=>	'REFUNDTYPE',
					'amt'			=>	'AMT',
					'currency_code' =>	'CURRENCYCODE',
					'inv_num'		=>	'INVOICEID',
					'note'			=>	'NOTE'
				)
			),
			'get_transaction_details'	=>	array(
				'api'	=>	'GetTransactionDetails',
				'required'	=>	array(
					'identifier'
				),
				'keymatch'	=>	array(
					'identifier'	=>	'TRANSACTIONID'
				)
			),
			'search_transactions'		=>	array(
				'api'	=>	'TransactionSearch',
				'required' => array(
					'start_date'
				),
				'keymatch' => array(
					'start_date'	=>	'STARTDATE',
					'end_date'		=>	'ENDDATE',
					'email'			=>	'EMAIL',
					'receiver'		=>	'RECEIVER',
					'receipt_id'	=>	'RECEIPTID',
					'transaction_id'=>	'TRANSACTIONID',
					'inv_num'		=>	'INVNUM',
					'cc_number'		=>	'ACCT',
					'transaction_class'	=>	'TRANSACTIONCLASS',
					'amt'			=>	'AMT',
					'currency_code'	=>	'CURRENCYCODE',
					'status'		=>	'STATUS',
					'salutation'	=>	'SALUTATION',
					'first_name'	=>	'FIRSTNAME',
					'middle_name'	=>	'MIDDLENAME',
					'last_name'		=>	'LASTNAME',
					'suffix'		=>	'SUFFIX'
				)
			),
			'recurring_payment'		=>	array(
				'api'	=>	'CreateRecurringPaymentsProfile',
				'required'	=>	array(
					'first_name',
					'last_name',
					'email',
					'amt',
					'cc_type',
					'cc_number',
					'cc_exp',
					'cc_code',
					'billing_period',
					'billing_frequency',
					'profile_start_date'
				),
				'keymatch' => array(
					'first_name'			=>	'SUBSCRIBERNAME',
					'last_name'				=>	'SUBSCRIBERNAME',
					'profile_start_date'	=>	'PROFILESTARTDATE',
					'profile_reference'		=>	'PROFILEREFERENCE',
					'desc'					=>	'DESC',
					'max_failed_payments'	=>	'MAXFAILEDPAYMENTS',
					'auto_bill_amt'			=>	'AUTOBILLAMT',
					'billing_period'		=>	'BILLINGPERIOD',
					'billing_frequency'		=>	'BILLINGFREQUENCY',
					'total_billing_cycles'	=>	'TOTALBILLINGCYCLES',
					'amt'					=>	'AMT',
					'currency_code'			=>	'CURRENCYCODE',
					'shipping_amt'			=>	'SHIPPINGAMT',
					'tax_amt'				=>	'TAXAMT',
					'trial_billing_cycles'	=>	'TRIALBILLINGPERIOD',
					'trial_billing_frequency'	=>	'TRIALBILLINGFREQUENCY',
					'trial_amt'				=>	'TRIALAMT',
					'cc_type'				=>	'CREDITCARDTYPE',
					'cc_number'				=>	'ACCT',
					'cc_exp'				=>	'EXPDATE',
					'cc_code'				=>	'CVV2',
					'email'					=>	'EMAIL',
					'identifier'			=>	'PAYERID',
					'country_code'			=>	'COUNTRY',
					'business_name'			=>	'BUSINESS',
					'salutation'			=>	'SALUTATION',
					'first_name'			=>	'FIRSTNAME',
					'middle_name'			=>	'MIDDLENAME',
					'last_name'				=>	'LASTNAME',
					'suffix'				=>	'SUFFIX',
					'street'				=>	'STREET',
					'street2'				=>	'STREET2',
					'city'					=>	'CITY',
					'state'					=>	'STATE',
					'postal_code'			=>	'ZIP'
				)
			),
			'get_recurring_profile'	=>	array(
				'api'	=>	'GetRecurringPaymentsProfileDetails',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'PROFILEID'
				)
			),
			'suspend_recurring_profile'	=>	array(
				'api'	=>	'ManageRecurringPaymentsProfileStatus',
				'required'	=>	array(
					'identifier'
				),
				'keymatch' => array(
					'note'			=>	'NOTE',
					'identifier'	=>	'PROFILEID'
				),
				'static' => array(
					'ACTION'	=>	'Suspend'
				)
			),
			'activate_recurring_profile' => array(
				'api'	=>	'ManageRecurringPaymentsProfileStatus',
				'required' => array(
					'identifier'
				),
				'keymatch' => array(
					'identifier' => 'PROFILEID',
					'note'		 =>	'NOTE'
				),
				'static'	=>	array(
					'ACTION'	=>	'Reactivate'
				)
			),
			'cancel_recurring_profile'	=>	array(
				'api'	=>	'ManageRecurringPaymentsProfileStatus',
				'required'	=>	array(
					'identifier'
				),
				'keymatch' => array(
					'identifier'	=>	'PROFILEID',
					'note'			=>	'NOTE'
				),
				'static'	=>	array(
					'ACTION' =>	'Cancel'
				)
			),
			'recurring_bill_outstanding'	=>	array(
				'api'	=>	'BillOutstandingAmount',
				'required'	=>	array(
					'identifier'
				),
				'keymatch' => array(
					'identifier'	=>	'PROFILEID',
					'amt'			=>	'AMT',
					'note'			=>	'NOTE'
				)
			),
			'update_recurring_profile'	=>	array(
				'api'	=>	'UpdateRecurringPaymentsProfile',
				'required'	=>	array(
					'identifier'
				),
				'keymatch' => array(
					'identifier'	=>	'PROFILEID',
					'note'			=>	'NOTE',
					'desc'			=>	'DESC',
					'subscriber_name'=> 'SUBSCRIBERNAME',
					'profile_reference' => 'PROFILEREFERENCE',
					'additional_billing_cycles' => 'ADDITIONALBILLINGCYCLES',
					'amt'			=>	'AMT',
					'shipping_amt'	=>	'SHIPPINGAMT',
					'tax_amt'		=>	'TAXAMT',
					'outstanding_amt'=> 'OUTSTANDINGAMT',
					'auto_bill_amt' =>	'AUTOBILLOUTAMT',
					'max_failed_payments' => 'MAXFAILEDPAYMENTS',
					'profile_start_date' => 'PROFILESTARTDATE',
					'total_billing_cycles' => 'TOTALBILLINGCYCLES',
					'currency_code'		=>	'CURRENCYCODE',
					'shipping_amt'	=>	'SHIPPINGAMT',
					'tax_amt'		=>	'TAXAMT',
					'cc_type'		=>	'CREDITCARDTYPE',
					'cc_number'		=>	'ACCT',
					'cc_exp'		=>	'EXPDATE',
					'cc_code'		=>	'CVV2',
					'start_date'	=>	'STARTDATE',
					'issue_number'	=>	'ISSUENUMBER',
					'email'			=>	'EMAIL',
					'first_name'	=>	'FIRSTNAME',
					'last_name'		=>	'LASTNAME',
					'street'		=>	'STREET',
					'street2'		=>	'STREET2',
					'city'			=>	'CITY',
					'state'			=>	'STATE',
					'country_code'	=>	'COUNTRY',
					'postal_code'	=>	'ZIP',
					'trial_amt'		=>	'TRIALAMT',
					'trial_total_billing_cycles'	=>	'TRIALTOTALBILLINGCYCLES'
				)
			)
		);
		return $map;
	}	

	/**
	 * Build the Request
	 *
	 * @param	array
	 * @return	array
	*/
	protected function _build_request($params)
	{
		//Normalize some param formats
		$params_adjusted = array();
		foreach($params as $k=>$v)
		{
			if($k == 'currency_code')
			{
				$val = strtoupper($v);
			}
			else
			{
				$val = $v;
			}

			$params_adjusted[$k] = $val;
		}

		$args = $this->_match_params($params_adjusted);
		$request = http_build_query(array_merge(array('METHOD' => $this->_api), $this->_settings, $args));
		return $request;
	}

	/**
	 * Match Params
	 *
	 * @param array
	 * @return	array
	*/
	private function _match_params($params)
	{
		$l = $this->_lib_method;
		$map = $this->method_map();
		$this->_api = $map[$l]['api'];
	
		$fields = array();
		foreach($params as $k=>$v)
		{
			if(isset($map[$l]['keymatch'][$k]))
			{
				$key = $map[$l]['keymatch'][$k];
				if(!isset($fields[$key]))
				{
					$fields[$key] = $v;
				}
				else
				{
					$fields[$key] .= " $v";
				}
			}
			else
			{
				error_log("$k is not a valid param for the $l method of the Paypal PaymentsPro driver.");
			}
		}
		
		if(isset($map[$l]['static']))
		{
			$static = $map[$l]['static'];
	
			foreach($static as $k=>$v)
			{
				$fields[$k] = $v;
			}
		}

		return $fields;
	}

	/**
	 * Parse the response from the server
	 *
	 * @param	array
	 * @return	object
	 */		
	protected function _parse_response($response)
	{	
	
		if($response === FALSE)
		{
			return Payment_Response::instance()->gateway_response(
				'Failure',
				$this->_lib_method.'_gateway_failure'
			);			
		}
		
		$results = explode('&',urldecode($response));
		foreach($results as $result)
		{
			list($key, $value) = explode('=', $result);
			$gateway_response[$key]=$value;
		}
	
		$details = (object) array(
			'gateway_response' => (object) array()
		);
		foreach($gateway_response as $k=>$v)
		{
			$details->gateway_response->$k = $v;
		}

		if(isset($gateway_response['L_LONGMESSAGE0']))
		{
			$details->reason  =	$gateway_response['L_LONGMESSAGE0'];
		}

		if(isset($gateway_response['TIMESTAMP']))
		{
			$details->timestamp = $gateway_response['TIMESTAMP'];
		}
			
		if(isset($gateway_response['TRANSACTIONID']))
		{
			$details->identifier = $gateway_response['TRANSACTIONID'];
		}
			
		if(isset($gateway_response['PROFILEID']))
		{
			$details->identifier = $gateway_response['PROFILEID'];
		}				
			
		if($gateway_response['ACK'] == 'Success')
		{	
			return Payment_Response::instance()->gateway_response(
				'Success',
				$this->_lib_method.'_success',
				$details
			);
		}
		else
		{
			return Payment_Response::instance()->gateway_response(
				'Failure',
				$this->_lib_method.'_gateway_failure', 
				$details
			);		
		}	
	}	
}
