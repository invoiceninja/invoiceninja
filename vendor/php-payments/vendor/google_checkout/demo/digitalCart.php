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

 chdir("..");
// Include all the required files
require_once('library/googlecart.php');
require_once('library/googleitem.php');
require_once('library/googleshipping.php');
require_once('library/googletax.php');

Usecase();
function Usecase() {
  $merchant_id = "";  // Your Merchant ID
  $merchant_key = "";  // Your Merchant Key
  $server_type = "sandbox";
  $currency = "USD";
  $cart = new GoogleCart($merchant_id, $merchant_key, $server_type,
  $currency);
  $total_count = 1;
//  Check this URL for more info about the two types of digital Delivery
//  http://code.google.com/apis/checkout/developer/Google_Checkout_Digital_Delivery.html

//  Key/URL delivery
  $item_1 = new GoogleItem("Download Digital Item1",      // Item name
                           "With S/N", // Item description
                           $total_count, // Quantity
                           10.99); // Unit price
  $item_1->SetURLDigitalContent('http://example.com/download.php?id=15',
                                'S/N: 123.123123-3213',
                                "Download Item1");
  $cart->AddItem($item_1);
//  Email delivery 
  $item_2 = new GoogleItem("Email Digital Item2",      // Item name
                           "An email will be sent by the merchant", // Item description
                           $total_count, // Quantity
                           9.19); // Unit price
  $item_2->SetEmailDigitalDelivery('true');
  $cart->AddItem($item_2);
  
  // Add tax rules
  $tax_rule = new GoogleDefaultTaxRule(0.05);
  $tax_rule->SetStateAreas(array("MA", "FL", "CA"));
  $cart->AddDefaultTaxRules($tax_rule);
  
  // Specify <edit-cart-url>
  $cart->SetEditCartUrl("https://www.example.com/cart/");
  
  // Specify "Return to xyz" link
  $cart->SetContinueShoppingUrl("https://www.example.com/goods/");
  
  // Request buyer's phone number
  $cart->SetRequestBuyerPhone(true);

// Add analytics data to the cart if its setted
  if(isset($_POST['analyticsdata']) && !empty($_POST['analyticsdata'])){
    $cart->SetAnalyticsData($_POST['analyticsdata']);
  }
// This will do a server-2-server cart post and send an HTTP 302 redirect status
// This is the best way to do it if implementing digital delivery
// More info http://code.google.com/apis/checkout/developer/index.html#alternate_technique
  list($status, $error) = $cart->CheckoutServer2Server();
  // if i reach this point, something was wrong
  echo "An error had ocurred: <br />HTTP Status: " . $status. ":";
  echo "<br />Error message:<br />";
  echo $error;
//
}
?>