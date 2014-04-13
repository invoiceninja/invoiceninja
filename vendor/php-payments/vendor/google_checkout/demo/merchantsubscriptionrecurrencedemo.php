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

  /**
  * This is an example for Merchant handled subscriptions.  This code will generate a 
  * recurrence request.
  */
  
  chdir("..");
  require_once('library/googlerequest.php');
  require_once('library/googleitem.php');
  
  define('RESPONSE_HANDLER_ERROR_LOG_FILE', 'googleerror.log');
  define('RESPONSE_HANDLER_LOG_FILE', 'googlemessage.log');

  $merchant_id = "";  // Your Merchant ID
  $merchant_key = "";  // Your Merchant Key
  $server_type = "sandbox";  // change this to go live
  $currency = 'USD';  // set to GBP if in the UK
  $google_order_id = ""; //google order id of recurrence order
  
  $Grequest = new GoogleRequest($merchant_id, $merchant_key, $server_type, $currency);
  
  $item1 = new GoogleItem("recurring item", "recurring item fee", 1, 30.00);
  $item1->SetCurrency($currency);
  $items = array($item1);
  $Grequest->SendRecurrenceRequest($google_order_id, $items);
?>