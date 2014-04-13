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
 * This example creates 2 buttons one for a Google Handled subscription and one for a Merchant Handled
 */
  chdir("..");
  
  require_once('library/googlecart.php');
  require_once('library/googleitem.php');
  require_once('library/googlesubscription.php');

    
  MerchantSubscription();
  GoogleSubscription();
  
  function MerchantSubscription() {
    echo "<h2>Merchant Handled Subscription Request</h2>";
    
    $merchant_id = "";  // Your Merchant ID
    $merchant_key = "";  // Your Merchant Key
    $server_type = "sandbox";  // or production
    $currency = "USD";
    
    $cart = new GoogleCart($merchant_id, $merchant_key, $server_type, $currency);
    
    
    $item = new GoogleItem("fee", "sign up fee", 1, 12.00);
    $subscription_item = new GoogleSubscription("merchant", "DAILY", 30.00);
    
    $item->SetSubscription($subscription_item);
    $cart->AddItem($item);
    
    echo $cart->CheckoutButtonCode("SMALL");
  }
  
  function GoogleSubscription() {
    echo "<h2>Google Handled Subscription Request</h2>";
    
    $merchant_id = "";  // Your Merchant ID
    $merchant_key = "";  // Your Merchant Key
    $server_type = "sandbox";  // or production
    $currency = "USD";
    
    $cart = new GoogleCart($merchant_id, $merchant_key, $server_type, $currency);
    
    $item = new GoogleItem("fee", "sign up fee", 1, 12.00);
    $subscription_item = new GoogleSubscription("google", "DAILY", 30.00);
    $recurrent_item = new GoogleItem("fee", "recurring fee", 1, 30.00);
    $subscription_item->SetItem($recurrent_item);
    $item->SetSubscription($subscription_item);
    $cart->AddItem($item);
    
    echo $cart->CheckoutButtonCode("MEDIUM");
    
  }
?>