<?php
/*
 * Copyright (C) 2010 Google Inc.
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
 * 
 * 
 */
 
 /** This class handles the notification history requests it's instantiated to
 * create notification history requests.
 *
 * refer to /demo/basicapiresponsehandlerdemo.php for a API V2.5 example.
 */
  class GoogleNotificationHistoryRequest {
     var $merchant_id;
     var $merchant_key;
     var $server_type;

     var $log;
     var $schema_url;
     
    /**
    * @param string $id merchant Id
    * @param string $key merchant Key
    * @param string $server_type server environment production or sandbox
    */ 
    function GoogleNotificationHistoryRequest($id=null, $key=null, $server_type="sandbox"){
     require_once('googlerequest.php');
     $this->merchant_id = $id;
     $this->merchant_key = $key;
     $this->server_type = $server_type;
     $this->schema_url = "http://checkout.google.com/schema/2";
    }

    /**
    * Send a <notification-history-request> request to Google Checkout
    *
    * info: {@link http://code.google.com/apis/checkout/developer/Google_Checkout_XML_API_Notification_History_API.html}
    *
    * @param string $sn serial number
    * @param string $npt next page token
    * @param array $orders array of string google order numbers
    * @param array $nt array of string notification types
    * @param array $st array of tracking data where tracking code => carrier
    * @param string @st string of start time in format YYYY-MM-DD[T]HH:MM:SS[Timezone] ie
    *        2010-05-01T05:00:00Z
    * @param string @et string of end time in format YYYY-MM-DD[T]HH:MM:SS[Timezone] ie
    *        2010-05-02T05:00:00Z
    */
    function SendNotificationHistoryRequest($sn = null, $npt = null, $orders = array(), $nt = array(), $st = null, $et = null){
     $postargs = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
     $postargs .= "<notification-history-request xmlns=\"".$this->schema_url."\">";
     if(isset($sn)){
       $postargs .= "<serial-number>".$sn."</serial-number>";
     } elseif(isset($npt)) {
       $postargs .= "<next-page-token>".$npt."</next-page-token>";
     }
     else{
       if(isset($orders) && count($orders) > 0){
         $postargs .= "<order-numbers>";
         foreach ($orders as $order){
           $postargs .= "<google-order-number>".$order."</google-order-number>";
         }
         $postargs .= "</order-numbers>";
       }
       if(isset($nt) && count($nt) > 0){
         $postargs .= "<notification-types>";
         foreach ($nt as $notification_type){
           $postargs .= "<notification-type>".$notification_type."</notification-type";
         }
         $postargs .= "</notification-types>";
       }
       if(isset($st) && isset($et)){
         $postargs .= "<start-time>".$st."</start-time>";
         $postargs .= "<end-time".$et."</end-time>";
       }
     }
     $postargs .= "</notification-history-request>";

     $Grequest = new GoogleRequest($this->merchant_id, $this->merchant_key, $this->server_type);
     return $Grequest->SendReq($Grequest->GetReportUrl(), $Grequest->GetAuthenticationHeaders(), $postargs);
    }
  }
?>
