<?php
/*
|-------------------------------------------------------------------------
| Payment Gateway Credentials
|--------------------------------------------------------------------------
|
| Fill out the following information with those provided 
| to you by your gateway.
| 
*/
$config['api_secret_key']	= "XXXXXXXXXXX"; // Bluepay Secret Key (NEVER sent directly over the wire)
$config['api_account_id']	= "00000000000"; // Bluepay 12 Digit ACCOUNT_ID
$config['api_user_id']		= "00000000000"; // Bluepay 12 Digit USER_ID (optional but helpful)

return $config;