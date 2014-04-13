<?php

$config = array(
	'access_key' => 'test',
	'secret_key' => 'test',
	'abandon_url' => 'http://yourwebsite.com/cancel', //Where user goes if they cancel the order
	'return_url' => 'http://yourwebsite.com/continue', //Where user goes after payment is finished
	'immediate_return' => '0', //Set to 1 if you want to skip the final status screen on amazon
	'process_immediate' => '0', //1 if you want to settle immediately.  Else it's simply an authorization and you must capture later.
	'ipn_url' => 'http://yourwebsite.com/ipn', //Where to send the ipn notification
	'collect_shipping_address' => '1', //1 tells amazon to collect buyer's shipping information
);

return $config;