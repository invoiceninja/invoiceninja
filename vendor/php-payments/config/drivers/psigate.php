<?php

$config['api_cid'] = "1000001";
$config['api_username'] = "teststore";
$config['api_password'] = "psigate1234";
$config['api_recurring_password'] = "testpass";
$config['api_endpoint_test'] = "https://dev.psigate.com:7989/Messenger/XMLMessenger";
$config['api_endpoint_production'] = ""; //YOU SHOULD RECEIVE THIS FROM PSIGATE AFTER SIGNING UP
/*
@TODO Recurring Billing Implementation
$config['api_recurring_endpoint_test'] = "https://dev.psigate.com:8645/Messenger/AMMessenger";
$config['api_recurring_endpoint_production'] = ""; //YOU SHOULD RECEIVE THIS FROM PSIGATE AFTER SIGNING UP
*/

return $config;