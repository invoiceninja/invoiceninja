<?php
/**
 * Response Details
 * 
 * Additional details to help in debugging
*/
$lang = array (
	'invalid_billing_period'	=>	'Billing period must be formatted as "Month", "Date", "Year" or "Week."',
	'invalid_date_format'		=>	'Dates must be provided in MMYYYY format.',
	'invalid_amount_format'		=>  'Money amounts must be formatted in decimal format, rounded to the hundredths place, ie 30.00',
	'missing_ip_address'		=>	'IP address is required but was not provided in the request',
	'missing_cc_type'			=>	'Credit Card Type is required but was not provided in the request',
	'missing_cc_number'			=>	'Credit Card Number is required but was not provided in the request',
	'missing_cc_details'		=>	'Full Credit Card details must be provided.',  
	'missing_cc_exp'			=>	'Credit Card Expiration is required but was not provided in the request',
	'missing_cc_code'			=>	'Credit Card code is required but was not provided in the request',
	'missing_email'				=>	'Email is required but was not provided in the request',
	'missing_street'			=>	'Street is required but was not provided in the request',
	'missing_city'				=>	'City is required but was not provided in the request',
	'missing_state'				=>	'State is required but was not provided in the request',
	'missing_country'			=>	'Country is required but was not provided in the request',
	'missing_postal_code'		=>	'Postal code is required but was not provided in the request',
	'missing_amt'				=>	'Amount is required but was not provided in the request',
	'missing_identifier'		=>	'An identifier (such as a previous transaction ID) is required but was not provided in the request',
	'missing_action'			=>	'An action to be taken by the payment gateway is required but was not provided in the request',
	'missing_refund_type'		=>	'The type of refund you are issuing is required but was not provided in the request',
	'missing_start_date'		=>	'Start date is required but was not provided in the request',
	'missing_profile_start_date'=>	'Profile start date is required but was not provided in the request',
	'missing_billing_period'	=>	'Billing period is required but was not provided in the request',
	'missing_billing_frequency'	=>	'Billing frequency is required but was not provided in the request',
	'missing_desc'				=>	'Description (desc) is required but was not provided in the request',
	'missing_first_name' 		=>  'First name is required but was not provided in the request',
	'missing_last_name'			=>  'Last name is required but was not provided in the request',
	'missing_currency_code'		=> 	'Currency is required but was not provided in the request',
	'no_details'				=> 	'No further details provided',
	'is_not_a_param'			=>  'Is not a valid parameter for this method',
);

return $lang;