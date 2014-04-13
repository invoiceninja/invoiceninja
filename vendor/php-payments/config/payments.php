<?php

/**
 * Set mode to test or production.  This determines which endpoints are used.
 * 
 * DEFAULT: test 
 */
$config['mode'] = 'test';

/**
 * Force Secure Connection. Should only be turned to FALSE if testing.
 * 
 * DEFAULT: TRUE 
 */
$config['force_secure_connection'] = FALSE;

/**
 * Sets the language to be used
 */
$config['language'] = 'english';

/**
 * Response Codes
 * 000 - Local Failure
 * 011 - Failure at Payment Gateway
 * 100 - Success!
*/
$config['response_codes'] = array (
	'not_a_module'									=>	'000',
	'invalid_input' 								=>	'000',
	'not_a_method'									=>	'000',
	'required_params_missing'						=>	'000',
	'invalid_xml' 									=>  '000',
	'authentication_failure'						=>	'011',

	//Payment Methods
	'authorize_payment_success'						=>	'100',
	'authorize_payment_local_failure'				=>	'000',	
	'authorize_payment_gateway_failure'				=>	'011',	
	'oneoff_payment_success'						=>	'100',
	'oneoff_payment_local_failure'					=>	'000',
	'oneoff_payment_gateway_failure'				=>	'011',
	'oneoff_payment_button_success'					=>	'100',
	'oneoff_payment_button_local_failure'			=>	'000',
	'oneoff_payment_button_gateway_failure'			=>	'011',	
	'reference_payment_success'						=>	'100',
	'reference_payment_local_failure'				=>	'000',
	'reference_payment_gateway_failure'				=>	'011',	
	'capture_payment_success'						=>	'100',
	'capture_payment_local_failure'					=>	'000',
	'capture_payment_gateway_failure'				=>	'011',
	'void_payment_success'							=>	'100',
	'void_payment_local_failure'					=>	'000',
	'void_payment_gateway_failure'					=>	'011',
	'void_refund_success'							=>	'100',
	'void_refund_local_failure'						=>	'000',
	'void_refund_gateway_failure'					=>	'011',	
	'get_transaction_details_success'				=>	'100',
	'get_transaction_details_local_failure'			=>	'000',
	'get_transaction_details_gateway_failure'		=>	'011',
	'change_transaction_status_success'				=>	'100',
	'change_transaction_status_local_failure'		=>	'000',	
	'change_transaction_status_gateway_failure'		=>	'011',
	'refund_payment_success'						=>	'100',
	'refund_payment_local_failure'					=>	'000',
	'refund_payment_gateway_failure'				=>	'011',	
	'search_transactions_success'					=>	'100',
	'search_transactions_local_failure'				=>	'000',
	'search_transactions_gateway_failure'			=>	'011',	
	'recurring_payment_success'						=>	'100',
	'recurring_payment_local_failure'				=>	'000',	
	'recurring_payment_gateway_failure'				=>	'011',		
	'get_recurring_profile_success'					=>	'100',
	'get_recurring_profile_local_failure'			=>	'000',
	'get_recurring_profile_gateway_failure'			=>	'011',		
	'suspend_recurring_profile_success'				=>	'100',
	'suspend_recurring_profile_local_failure'		=>	'000',
	'suspend_recurring_profile_gateway_failure'		=>	'011',		
	'activate_recurring_profile_success'			=>	'100',
	'activate_recurring_profile_local_failure'		=>	'000',
	'activate_recurring_profile_gateway_failure'	=>	'011',		
	'cancel_recurring_profile_success'				=>	'100',
	'cancel_recurring_profile_local_failure'		=>	'000',
	'cancel_recurring_profile_gateway_failure'		=>	'011',		
	'recurring_bill_outstanding_success'			=>	'100',
	'recurring_bill_outstanding_local_failure'		=>	'000',
	'recurring_bill_outstanding_gateway_failure'	=>	'011',		
	'update_recurring_profile_success'				=>	'100',
	'update_recurring_profile_local_failure'		=>	'000',
	'update_recurring_profile_gateway_failure'		=>	'011',		
	'token_create_success'							=>	'100',
	'token_create_local_failure'					=>	'000',
	'token_create_gateway_failure'					=>	'011',
	'customer_create_success'						=>	'100',
	'customer_create_local_failure'					=>	'000',
	'customer_create_gateway_failure'				=>	'011',
	'customer_charge_success'						=>	'100',
	'customer_charge_local_failure'					=>	'000',
	'customer_create_gateway_failure'				=>	'011'
);

return $config;
