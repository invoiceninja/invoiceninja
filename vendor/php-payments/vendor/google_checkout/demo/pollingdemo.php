
<?php
/*
 * Copyright (C) 2007 Google Inc.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *      http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// Point to the correct directory
chdir("..");

// Include all the required files
require_once('library/googlepoll.php');

$merchant_id = "563676382451138";
$merchant_key = "zvoEMXHhkdhVtGEOju2lHw";
$environment = "sandbox";
$tokenRequest = new ContinueTokenRequest($merchant_id, $merchant_key, $environment);
$tokenRequest->SetStartTime("2008-04-01T18:25:31");
$contToken = $tokenRequest->RequestToken();

if($contToken !="false") {
	$poll = new GooglePoll($merchant_id, $merchant_key, $environment, $contToken);
	$poll->GetAllNotifications(false);
	$pollSuccessful = $poll->RequestData();

	if($pollSuccessful == true) {
		$notifications = $poll->GetNotifications();
		
		echo "Total Number of Notifications retrieved: " .sizeof($notifications) ."<br><br>";
		
		foreach($notifications as $notification) {
			print_r($notification );
			echo "<br><br>";
		}
	}
}
?>