<?php

$vals = array(
	'all' => array(
		'desc' => 'This is a description',
		'cc_number' => '4111111111111111',
		'cc_code' => '203',
		'cc_type' => 'Visa',
		'cc_exp' => '022016',
		'amt' => '3.00',
		'first_name' => 'John',
		'last_name' => 'Doe',
		'street' => '401 Somewhere street',
		'city' => 'Cookeville',
		'state' => 'TN',
		'postal_code' => '38501',
		'email' => 'johndoe@gmail.com',
		'desc' => 'Testing',
		'profile_start_date' => '2015-05-31',
		'start_date' => '2012-05-31',
		'billing_period' => 'Month',
		'billing_frequency' => '1',
		'total_billing_cycles' => '9999',
		'country_code' => 'US',
		'currency_code' => 'usd',
		'phone' => '(239) 239 2392'
	),
	'authorize_net_driver' => array(
		'cc_number' => '4997662409617853',
	),
	'paypal_paymentspro_driver' => array(
		'cc_number' => '4997662409617853'
	),
	'google_checkout_driver' => array(
		'edit_url' => 'http://test.me',
		'continue_url' => 'http://test.me'
	),
	'eway_driver' => array(
		'cc_number' => 4444333322221111
	),
	'beanstream_driver' => array(
		'cc_number' => 4030000010001234
	)
);

return $vals;
