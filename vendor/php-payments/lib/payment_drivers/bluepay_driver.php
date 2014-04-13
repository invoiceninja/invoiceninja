<?php

/**
 * BluePay Payment Module
 *
 * @author Joel Kallman (www.eclarian.com)
 * @email jkallman@eclarian.com
 * @created 08/24/2011
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Bluepay_Driver extends Payment_Driver {

	/**
	 * The API endpoint
	 */
	private $_api_endpoint = 'https://secure.bluepay.com/interfaces/bp20post';	
	
	/**
	 * The API method currently being utilized
	 */
	private $_api_method;
	
	/**
	 * An array for storing all settings
	 */	
	private $_api_settings;

	/**
	 * The version of the API to use
	 */	
	private $_api_version = '2';
	
	/**
	 * The lib method
	 */	
	private $_lib_method;	

	// -------------------------------------------------------------------------
	
	/**
	 * Constructor method
	 */		
	public function __construct($config)
	{
		$this->_api_settings = array(
			'ACCOUNT_ID'	=> $config['api_account_id'],
			'PAYMENT_TYPE'	=> 'CREDIT', //means credit card - not giving away money!
			'SECRET_KEY'	=> $config['api_secret_key'],
			'MODE'			=> ($config['mode'] == 'production') ? 'LIVE' : 'TEST'
		);

		if(!empty($config['api_user_id'])) $this->_api_settings['USER_ID'] = $config['api_user_id'];
	}

	/**
	 * Caller Magic Method
	*/
	public function __call($method, $params)
	{
		$this->_lib_method = $method;
		$args = $params[0];
		$request_string = $this->_build_request($params);

		$raw_response = Payment_Request::curl_request($this->_api_endpoint, $request_string, 'application/x-www-form-urlencoded');
		return $this->_parse_response($raw_response);
	}
	
	/**
	 * Maps Methods to Their Attributes
	*/
	public function method_map()
	{
		$map = 
		array(
		'oneoff_payment' => array(
			'method' => 'SALE',
			'required' => array(
				'cc_number',
				'cc_code',
				'cc_exp',
				'amt'
			),
			'keymatch' => array(
	            'cc_number'         => 'PAYMENT_ACCOUNT', //Credit card number
 			    'cc_exp'            => 'CARD_EXPIRE', //Must be formatted MMYYYY @todo - Must translate to MMYY
   	    	    'cc_code'           => 'CARD_CVV2', //3 or 4 digit cvv code
            	'email'             => 'EMAIL', //email associated with account being billed
	            'first_name'        => 'NAME1', //first name of the purchaser
    	        'last_name'         => 'NAME2', //last name of the purchaser
        	    'business_name'     => 'COMPANY_NAME', //name of business
            	'street'            => 'ADDR1', //street address of the purchaser @todo - Only 64 Char
 	            'street2'           => 'ADDR2', //street address 2 of purchaser @todo - Only 64 Char
        	    'city'              => 'CITY', //city of the purchaser @todo - Only 32 Char
    	        'state'             => 'STATE', //state of the purchaser @todo - Only 16 Char; 2 lttr abbr pref.
        	    'country'           => 'COUNTRY', // country of the purchaser (64 Char)
       	    	'postal_code'       => 'ZIP', //zip code of the purchaser (16 Char)
            	'amt'               => 'AMOUNT', //purchase amount (XXXXXXX.XX FORMAT) Includes Tax and Tip
            	'phone'             => 'PHONE', //phone num of customer shipped to @todo - Required for ACH; 16 Chars.
            	'tax_amt'           => 'AMOUNT_TAX', //Amount for just tax.
            	'desc'              => 'MEMO', //Description for the transaction
            	'inv_num'           => 'INVOICE_ID', //Invoice number @todo - 64 Characters
            	'po_num'            => 'ORDER_ID',
			)
		),
		'authorize_payment' => array(
			'method' => 'AUTH',
			'required' => array(
				'cc_number',
				'cc_code',
				'cc_exp',
				'amt'
			),
			'keymatch' => array(
	            'cc_number'         => 'PAYMENT_ACCOUNT', //Credit card number
 			    'cc_exp'            => 'CARD_EXPIRE', //Must be formatted MMYYYY @todo - Must translate to MMYY
   	    	    'cc_code'           => 'CARD_CVV2', //3 or 4 digit cvv code
            	'email'             => 'EMAIL', //email associated with account being billed
	            'first_name'        => 'NAME1', //first name of the purchaser
    	        'last_name'         => 'NAME2', //last name of the purchaser
        	    'business_name'     => 'COMPANY_NAME', //name of business
            	'street'            => 'ADDR1', //street address of the purchaser @todo - Only 64 Char
 	            'street2'           => 'ADDR2', //street address 2 of purchaser @todo - Only 64 Char
        	    'city'              => 'CITY', //city of the purchaser @todo - Only 32 Char
    	        'state'             => 'STATE', //state of the purchaser @todo - Only 16 Char; 2 lttr abbr pref.
        	    'country'           => 'COUNTRY', // country of the purchaser (64 Char)
       	    	'postal_code'       => 'ZIP', //zip code of the purchaser (16 Char)
            	'amt'               => 'AMOUNT', //purchase amount (XXXXXXX.XX FORMAT) Includes Tax and Tip
            	'phone'             => 'PHONE', //phone num of customer shipped to @todo - Required for ACH; 16 Chars.
            	'tax_amt'           => 'AMOUNT_TAX', //Amount for just tax.
            	'desc'              => 'MEMO', //Description for the transaction
            	'inv_num'           => 'INVOICE_ID', //Invoice number @todo - 64 Characters
            	'po_num'            => 'ORDER_ID',
			)
		),
		'capture_payment' => array(
			'method' => 'CAPTURE',
			'required' => array(
				'identifier'
			),
			'keymatch' => array(
            	'identifier'        => 'MASTER_ID', //Merchant provided identifier for the transaction @todo - IS PREVIOUS TRANS_ID AND ONLY REQUIRED FOR CAPTURE OR REFUND.
			)
		),
		'refund_payment' => array(
			'method' => 'REFUND',
			'required' => array(
				'identifier'
			),
			'keymatch' => array(
            	'identifier'        => 'MASTER_ID', //Merchant provided identifier for the transaction @todo - IS PREVIOUS TRANS_ID AND ONLY REQUIRED FOR CAPTURE OR REFUND.
			)
		),
		'void_payment' => array(
			'method' => 'VOID',
			'required' => array(
				'identifier'
			),
			'keymatch' => array(
            	'identifier'        => 'MASTER_ID', //Merchant provided identifier for the transaction @todo - IS PREVIOUS TRANS_ID AND ONLY REQUIRED FOR CAPTURE OR REFUND.
			)
		)
		);

		return $map;
	}
	
	// -------------------------------------------------------------------------
	
	/**
	 * Builds a request
	 *
	 * Builds as an HTTP POST Request
	 *
	 * @param	array	array of params
	 * @param	string	the api call to use
	 * @return	array	Array of transaction settings
	*/	
	protected function _build_request($params)
	{
		$params_ready = array();
		
		// Map CI Payments Keys to Gateway Keys
		$map = $this->method_map();
		foreach($map as $k => $v)
		{
			// Key not being used or Parameter not included or empty
			if($v === FALSE OR ! isset($params[$k]) OR empty($params[$k])) continue;			
			$params_ready[$v] = $params[$k];
		}
		
		$params_ready['TRANS_TYPE']	= $this->_lib_method;
		$params_ready['TAMPER_PROOF_SEAL'] = $this->_build_tamper_proof_seal(array_merge($params_ready, $this->_api_settings));

		// Build HTTP Query Because we are using POST rather than XML
		return http_build_query(array_merge($this->_api_settings ,$params_ready));
	}

	// -------------------------------------------------------------------------
	
	/**
	 * Build Tamper Proof Seal
	 *
	 * This function creates a md5 checksum to validate the integrity of the request
	 * The secret key is never passed directly and is used as a salt to provide a check
	 * on the gateway servers.
	 * 
	 * FORMAT:
	 * md5(SECRET KEY + ACCOUNT_ID + TRANS_TYPE + AMOUNT + MASTER_ID + NAME1 + PAYMENT_ACCOUNT)
	 * 
	 * @param	array	Current Requests Parameters
	 * @return	string	Checksum for Tamper Proof Seal
	 */
	protected final function _build_tamper_proof_seal($params)
	{
		$hash = '';
		$tps_contents = array('SECRET_KEY', 'ACCOUNT_ID', 'TRANS_TYPE', 'AMOUNT', 'MASTER_ID', 'NAME1', 'PAYMENT_ACCOUNT');		
		foreach($tps_contents as $key) $hash .= (isset($params[$key])) ? $params[$key]: '';
		return bin2hex( md5($hash, TRUE) );		
	}
	
	// -------------------------------------------------------------------------
	
	/**
	 * Build the query for the response and call the request function
	 *
	 * @param	array
	 * @param	array
	 * @param	string
	 * @return	array
	 */		
	protected function _handle_query()
	{	
		$this->_http_query = $this->_request;
	}
	
	// -------------------------------------------------------------------------
	
	/**
	 * Parse the response from the server
	 *
	 * @param	object	Always includes timestamp, gateway_response, reason
	 * @return	object
	 */		
	protected function _parse_response($data)
	{
		// Since this module currently uses POST to make the gateway request
		// We know our current object can be simply typecasted back to an array.
		// IF THIS EVER CHANGES, USE $this->payments->arrayize_object($data);
		$results = explode('&',urldecode($data));
		foreach($results as $result)
		{
			list($key, $value) = explode('=', $result);
			$gateway_response[$key]=$value;
		}		
		
		$details = (object) array();
		$details->timestamp = gmdate('c');
		$details->gateway_response = $gateway_response; // Full Gateway Response		
		
		//Set response types
		$response_types = array(
			'E' => $this->_lib_method.'_gateway_failure', 
			'1' => $this->_lib_method.'_success', 
			'0' => $this->_lib_method.'_local_failure'
		);
		
		// Default to Failure if data is not what is expected
		$status = 'failure';
		
		// Setup Final Response 
		if(isset($gateway_response['MESSAGE']))
		{		
			$details->reason = $gateway_response['MESSAGE'];
		}
		
		if(isset($gateway_response['STATUS']))
		{
			$details->status = $gateway_response['STATUS']; // The request can be successful, yet have the card be declined
		}
		
		// Setup additional properties if successful
		if(isset($gateway_response['TRANS_ID']))
		{
			$details->identifier = $gateway_response['TRANS_ID'];
		}
				
		// Return Local Response, because we didn't get an expected response from server
		if( ! isset($gateway_response['STATUS'], $gateway_response['MESSAGE']))
		{
			// @todo - Don't know if this should be a different response than "gateway" 
			return Payment_Response::instance()->gateway_response($status, $response_types['E'], $details);
		}
				
		// Possible Responses are 1 = Approved, 0 = Decline, 'E' = Error
		$is_success = ($data['STATUS'] === '1');
		
		// Setup Response
		$status = ($is_success) ? 'success': 'failure';
		$response = $response_types[$gateway_response['STATUS']];
		
		// Send it back!	
		return Payment_Response::instance()->gateway_response($status, $response, $details);
	}

	// -------------------------------------------------------------------------	
}