<?php

/**
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

 /* This is the response handler code that will be invoked every time
  * a notification or request is sent by the Google Server.  This code is
  * targeted at Google Checkout API v2.5 but can also handle previous versions
  * 
  * To allow this code to receive responses, the url for this file
  * must be set on the seller page under Settings->Integration as the
  * "API Callback URL"
  * Order processing commands can be sent automatically by placing these
  * commands appropriately.  The charge and ship example is provided for you.
  *
  */

  chdir("..");
  require_once('library/googleresponse.php');
  require_once('library/googlemerchantcalculations.php');
  require_once('library/googlerequest.php');
  require_once('library/googlenotificationhistory.php');
  
  define('RESPONSE_HANDLER_ERROR_LOG_FILE', 'googleerror.log');
  define('RESPONSE_HANDLER_LOG_FILE', 'googlemessage.log');

  //Definitions
  $merchant_id = "";  // Your Merchant ID
  $merchant_key = "";  // Your Merchant Key
  $server_type = "sandbox";  // change this to go live
  $currency = 'USD';  // set to GBP if in the UK
  
  //Create the response object
  $Gresponse = new GoogleResponse($merchant_id, $merchant_key);

  //Setup the log file
  $Gresponse->SetLogFiles('', '', L_OFF);  //Change this to L_ON to log

  //Retrieve the XML sent in the HTTP POST request to the ResponseHandler
  $xml_response = isset($HTTP_RAW_POST_DATA)?
                    $HTTP_RAW_POST_DATA:file_get_contents("php://input");
  
  //If serial-number-notification pull serial number and request xml
  if(strpos($xml_response, "xml") == FALSE){
    //Find serial-number ack notification
    $serial_array = array();
    parse_str($xml_response, $serial_array);
    $serial_number = $serial_array["serial-number"];
    
    //Request XML notification
    $Grequest = new GoogleNotificationHistoryRequest($merchant_id, $merchant_key, $server_type);
    $raw_xml_array = $Grequest->SendNotificationHistoryRequest($serial_number);
    if ($raw_xml_array[0] != 200){
      //Add code here to retry with exponential backoff
    } else {
      $raw_xml = $raw_xml_array[1];
    }
    $Gresponse->SendAck($serial_number, false);
  }
  else{
    //Else assume pre 2.5 XML notification
    //Check Basic Authentication
    $Gresponse->SetMerchantAuthentication($merchant_id, $merchant_key);
    $status = $Gresponse->HttpAuthentication();
    if(! $status) {
      die('authentication failed');
    }
    $raw_xml = $xml_response;
    $Gresponse->SendAck(null, false);
  }

  if (get_magic_quotes_gpc()) {
    $raw_xml = stripslashes($raw_xml);
  }
  
  //Parse XML to array
  list($root, $data) = $Gresponse->GetParsedXML($raw_xml);
  
  /* Commands to send the various order processing APIs
   * Send charge order : $Grequest->SendChargeOrder($data[$root]
   *    ['google-order-number']['VALUE'], <amount>);
   * Send process order : $Grequest->SendProcessOrder($data[$root]
   *    ['google-order-number']['VALUE']);
   * Send deliver order: $Grequest->SendDeliverOrder($data[$root]
   *    ['google-order-number']['VALUE'], <carrier>, <tracking-number>,
   *    <send_mail>);
   * Send archive order: $Grequest->SendArchiveOrder($data[$root]
   *    ['google-order-number']['VALUE']);
   *
   */
  switch($root){
    case "new-order-notification": {
      break;
    }
    case "risk-information-notification": {
      break;
    }
    case "charge-amount-notification": {
      break;
    }
    case "authorization-amount-notification": {
      $google_order_number = $data[$root]['google-order-number']['VALUE'];
      $tracking_data = array("Z12345" => "UPS", "Y12345" => "Fedex");
      $GChargeRequest = new GoogleRequest($merchant_id, $merchant_key, $server_type);
      $GChargeRequest->SendChargeAndShipOrder($google_order_number, $tracking_data);
      break;
    }
    case "refund-amount-notification": {
      break;
    }
    case "chargeback-amount-notification": {
      break;
    }
    case "order-numbers": {
      break;
    }
    case "invalid-order-numbers": {
      break;
    }
    case "order-state-cahnge-notification": {
      break;
    }
    default: {
      break;
    }
  }

  /* In case the XML API contains multiple open tags
     with the same value, then invoke this function and
     perform a foreach on the resultant array.
     This takes care of cases when there is only one unique tag
     or multiple tags.
     Examples of this are "anonymous-address", "merchant-code-string"
     from the merchant-calculations-callback API
  */
  function get_arr_result($child_node) {
    $result = array();
    if(isset($child_node)) {
      if(is_associative_array($child_node)) {
        $result[] = $child_node;
      }
      else {
        foreach($child_node as $curr_node){
          $result[] = $curr_node;
        }
      }
    }
    return $result;
  }

  /* Returns true if a given variable represents an associative array */
  function is_associative_array( $var ) {
    return is_array( $var ) && !is_numeric( implode( '', array_keys( $var ) ) );
  }
?>
