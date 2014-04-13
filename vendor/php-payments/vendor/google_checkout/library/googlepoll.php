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

/**
 * Classes used to poll for Google Checkout notifications (using the polling API)
 */

  /**
   * Polls for notifications
   */
  class GooglePoll {
	
	var $continue_token;
	var $get_all_notifications = true;
	var $has_more_notifications = true;
	
	var $merchant_id;
	var $merchant_key;
	var $environment;
	var $poll_request_xml;
	var $poll_result;
	var $notifications = array();
	
	var $schema_url = "http://checkout.google.com/schema/2";
	var $prod_base_server_url = "https://checkout.google.com/api/checkout/v2/reports/Merchant/";
	var $sandbox_base_server_url = "https://sandbox.google.com/checkout/api/checkout/v2/reports/Merchant/";
	var $server_url;
	var $xml_data;
	
	/*
	 * Constructor for the class
	 * Inputs are: merchant id, merchant key, environment (default 'sandbox')
	 * and a continue-token (from a ContinueTokenRequest)
	 */
	function GooglePoll ($id, $key, $env, $contToken) {
		$this->merchant_id = $id;
		$this->merchant_key = $key;
		$this->environment = $env;
		$this->continue_token = $contToken;
		switch ($env) {
			case "production":
				$this->server_url = $this->prod_base_server_url .$id;
				break;
			default:
				$this->server_url = $this->sandbox_base_server_url .$id;
		}
	}
	/*
	 * Set whether polling should continue until there are no more
	 * appropriate notifications to fetch.  Default = true, value false
	 * will stop after one request.
	 */
	function GetAllNotifications ( $get_all) {
		switch ($get_more) {
			case false:
				$this->get_all_notifications = false;
				break;
			case true:
				$this->get_all_notifications = true;
				break;
			default:
				true;
		}
	}
	/*
	 * Polls for notifications as defined
	 */
	function RequestData () {

		//create GRequest object + post xml (googlecart.php line: 962)
		require_once('library/googlerequest.php');
		$GRequest = new GoogleRequest($this->merchant_id, $this->merchant_key);

		while($this->has_more_notifications == "true") {
			$this->poll_request_xml = $this->GetPollRequestXML();
			$this->poll_result = $GRequest->SendReq($this->server_url,
				$GRequest->GetAuthenticationHeaders(), $this->poll_request_xml);

			//Check response code
			if($this->poll_result[0] == "200") {
				$this->ExtractNotifications();
			}
			else return false;

			if($this->get_all_notifications == false) {
				$this->has_more_notifications == "false";
			}
		}
		return true;
	}
	/*
	 * Returns an array containing all notifications from poll.
	 * This includes notifications from multiple requests
	 */
	function GetNotifications () {
		return $this->notifications;
	}
	/*
	 * Extracts data from poll response xml.
	 * This includes, individual notifications, new continue token
	 * and more notifications value
	 */
	function ExtractNotifications () {
		require_once('xml-processing/gc_XmlParser.php');
		$GXmlParser = new gc_XmlParser($this->poll_result[1]);
			$data = $GXmlParser->GetData();
			//Get the actual notifications
			foreach($data['notification-data-response']['notifications'] as $notification) {
				$this->notifications[] = $notification;
			}
			//Get other useful info
			$this->has_more_notifications = $data['notification-data-response']['has-more-notifications']['VALUE'];
			$this->continue_token = $data['notification-data-response']['continue-token']['VALUE'];
	}
	/*
	 * Builds poll request XML
	 */
	function GetPollRequestXML() {
		require_once('xml-processing/gc_xmlbuilder.php');
		$xml_data = new gc_XmlBuilder();
		
		$xml_data->Push('notification-data-request',
          		array('xmlns' => $this->schema_url));
        		$xml_data->Element('continue-token', $this->continue_token);
        	$xml_data->Pop('notification-data-request');

        	return $xml_data->GetXML();
	}
  }

   /**
   * Requests a continue token for polling
   */
  class ContinueTokenRequest {
  	var $start_time;
	var $continue_token;
	
	var $merchant_id;
	var $merchant_key;
	var $environment;
	var $request_token_xml;
	var $token_response_xml;
	
	var $schema_url = "http://checkout.google.com/schema/2";
	var $prod_base_server_url = "https://checkout.google.com/api/checkout/v2/reports/Merchant/";
	var $sandbox_base_server_url = "https://sandbox.google.com/checkout/api/checkout/v2/reports/Merchant/";
	var $server_url;
	var $xml_data;
	
	function ContinueTokenRequest ($id, $key, $env) {
		$this->merchant_id = $id;
		$this->merchant_key = $key;
		$this->environment = $env;
		switch ($env) {
			case "production":
				$this->server_url = $this->prod_base_server_url .$id;
				break;
			default:
				$this->server_url = $this->sandbox_base_server_url .$id;
		}
	}
	function SetStartTime ($poll_start_time) {
		$this->start_time = $poll_start_time;
	}
	function GetContinueToken () {
		if($this->continue_token !="") {
			return $this->continue_token;
		}
		else return false;
	}
	function RequestToken () {
		$this->request_token_xml = $this->GetTokenRequestXML();
		
		//create GRequest object + post xml (googlecart.php line: 962)
		require_once('library/googlerequest.php');
		$GRequest = new GoogleRequest($this->merchant_id, $this->merchant_key);
		/*---------------------------------------------------------------------------------------------------*/$GRequest->SetCertificatePath("/etc/ssl/certs/ca-certificates.crt");

		$this->token_response_xml = $GRequest->SendReq($this->server_url,
			$GRequest->GetAuthenticationHeaders(), $this->request_token_xml);

		//Check response code
		if($this->token_response_xml[0] == "200") {
			require_once('xml-processing/gc_XmlParser.php');
			$GXmlParser = new gc_XmlParser($this->token_response_xml[1]);
			$data = $GXmlParser->GetData();

			$this->continue_token = $data['notification-data-token-response']['continue-token']['VALUE'];
			return $this->continue_token;
		}
		//else return $token_result;
		else return false;
	}
	function GetTokenRequestXML() {
		require_once('xml-processing/gc_xmlbuilder.php');
		$xml_data = new gc_XmlBuilder();
		
		$xml_data->Push('notification-data-token-request',
          		array('xmlns' => $this->schema_url));
        		$xml_data->Element('start-time', $this->start_time);
        	$xml_data->Pop('notification-data-token-request');

        	return $xml_data->GetXML();
	}
}
?>