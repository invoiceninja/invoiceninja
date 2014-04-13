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
 * This class handles Subscriptions, both merchant handled and google handled
 *
 * The example for using this class can be found in demos/subscriptiondemo.php and
 * merchantsubscriptionrecurrencedemo.php
 */
 class GoogleSubscription{
 
 var $subscription_type;
 var $subscription_period;
 var $subscription_no_charge_after;
 var $subscription_start_date;
 var $subscription_payment_times;
 
 var $maximum_charge;
 var $recurrent_item;
 
 /**
 * {@link http://code.google.com/apis/checkout/developer/Google_Checkout_Beta_Subscriptions.html
 *    <subscription>}
 *  @param string $type type of subscription google or merchant handled -- required
 *  @param string $period period to charge for subscriptions --required
 *    subscriptions
 *  @param int $times number of times the customer will be charged -- optional
 *  @param double $maximum maximum possible total for subscription period -- required
 *  @param googleitem $item recurrent-item to charge --optional for merchant handled
 */
   function GoogleSubscription($type, $period, $maximum, $times='',  $item='') {
     $this->subscription_type = $type;
     $this->subscription_period = $period;
     $this->maximum_charge = $maximum;
     if($times != '')
       $this->subscription_payment_times = $times;
     if($item != '')
       $this->recurrent_item = $item;
   }
   
   /**
   * Sets the start date of the subscription
   *
   * GC tag: {@link http://code.google.com/apis/checkout/developer/Google_Checkout_Beta_Subscriptions.html#tag_subscription}
   *
   * @param date start date of subscription
   */
   function SetStartDate($startdate){
     $this->subscription_start_date = $startdate;
   }
   
   /**
   * Sets the end date of the subscription
   *
   * GC tag: {@link http://code.google.com/apis/checkout/developer/Google_Checkout_Beta_Subscriptions.html#tag_subscription}
   *
   * @param date end date of subscription
   */
   function SetNoChargeAfter($nocharge){
     $this->subscription_no_charge_after = $nocharge;
   }
   
   /**
   * Sets the recurring item for Google 
   *
   * GC tag: {@link http://code.google.com/apis/checkout/developer/Google_Checkout_Beta_Subscriptions.html#tag_recurrent-item}
   *
   * @param item googleitem recurring item to charge
   */
   function SetItem($item){
     $this->recurrent_item = $item;
   }
 }
?>


