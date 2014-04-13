<?php
 
require_once('googlelog.php');

class GoogleNotification {
  var $merchant_id;
  var $merchant_key;
  
  var $start_date;
  var $end_date;
  var $orders;
  var $notification_types;
  var $timezone;
  var $continue_token;
  
  var $schema_url;
  var $server_url;
  var $base_url;
  var $reports_url;
  
  var $proxy;
  var $log;
  
  var $error;

  function GoogleNotification($id, $key, $server_type="sandbox", $proxy=null) {
    $this->merchant_id = $id;
    $this->merchant_key = $key;
    $this->proxy = $proxy;

    $this->schema_url = "http://checkout.google.com/schema/2";
    if(strtolower($server_type) == "sandbox"){ 
      $this->server_url = "https://sandbox.google.com/checkout/";
    }
    else {
      $this->server_url=  "https://checkout.google.com/";
    }  

    $this->base_url = $this->server_url . "api/checkout/v2/"; 
    $this->reports_url = $this->base_url . "reports/Merchant/" . 
                         $this->merchant_id;

    $this->log = new GoogleLog('', '', L_OFF);
  }
  
  function setLogFiles($errorLogFile, $messageLogFile, $logLevel=L_ERR_RQST) {
    $this->log = new GoogleLog($errorLogFile, $messageLogFile, $logLevel);
  }
  
  function getNotifications($start_date='', $end_date='', $orders=array(),
                            $notification_types=array(),
                            $continue_token='') {
    require_once('xml-processing/gc_xmlparser.php');
    require_once('xml-processing/gc_xmlbuilder.php');
    
    $this->start_date = $start_date;
    $this->end_date = $end_date;
    $this->orders = $orders;
    $this->notification_types = $notification_types;
    $this->continue_token = $continue_token;
    
    $notifications  = array();
    $invalid_order_numbers = array();
    
    do {
      list($status, $response) = $this->_doReportsRequest();
      if($status != '200') {
        $this->error = array($status, $response);                                  
        return null;
      }
      //echo '<xmp>'; print_r($response); echo '</xmp>';
      $xml_parser = new gc_XmlParser($response);
      $data = $xml_parser->GetData();
      $root = $xml_parser->GetRoot();
      
      $this->continue_token = $data[$root]['continue-token']['VALUE'];
      $notifications = $data[$root]['notifications'];
      $invalid_order_numbers = $data[$root]['invalid-order-numbers'];
    } while($data[$root]['has-more-notifications']['VALUE'] == 'true');
    
    return array($notifications, $invalid_order_numbers);
  }
  
  function _doReportsRequest() {
    $xml_data = new gc_XmlBuilder();
    $xml_data->Push('notification-history-request',
        array('xmlns' => $this->schema_url));

    if(!empty($this->continue_token)) {
      $xml_data->Element('continue-token', trim($this->continue_token));
    } else {  
      if(!empty($this->start_date)) {
        $xml_data->Element('start-time', trim($this->start_date));
      }
      if(!empty($this->end_date)) {
        $xml_data->Element('end-time', trim($this->end_date));
      }
      if(is_array($this->orders) && !empty($this->orders)) {
        $xml_data->Push('order-numbers');
        foreach($this->orders as $order) {
          if(trim($order) != ''){ 
            $xml_data->Element('google-order-number', trim($order));
          }
        }
        $xml_data->Pop('order-numbers');
      }
      if(is_array($this->notification_types) && !empty($this->notification_types)) {
        $xml_data->Push('notification-types');
        foreach($this->notification_types as $notification_type) {
          $xml_data->Element('notification-type', trim($notification_type));
        }
        $xml_data->Pop('notification-types');
      }
    }
    $xml_data->Pop('notification-history-request');
    return $this->sendRequest($this->reports_url, 
                              $this->getAuthenticationHeaders(),
                              $xml_data->GetXML());
  }
  
  function getError() {
    if($this->error[0] != 'CURLERR') {
      $xml_parser = new gc_XmlParser($this->error[1]);
      $data = $xml_parser->GetData();
      $error = $data['error']['error-message']['VALUE'];
    } else {
      $error = $this->error[1];
    }
    return array('status_code'=>$this->error[0], 'message'=>$error);
  }

  function getAuthenticationHeaders() {
    $headers = array(
      "Authorization: Basic " . 
                      base64_encode($this->merchant_id.':'.$this->merchant_key),
      "Content-Type: application/xml; charset=UTF-8",
      "Accept: application/xml; charset=UTF-8",
      "User-Agent: gc-php4-sample-code v1.2.5b");
    return $headers; 
  }
  
  function setProxy($proxy) {
    $this->proxy = $proxy;
  }

  function sendRequest($url, $header_arr, $postargs) {
    $ch = curl_init($url);
    $this->log->LogRequest($postargs);
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header_arr);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postargs);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if($this->proxy) {
       curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
    }
    
    $body = curl_exec($ch);
    
    if (curl_errno($ch)) {
      $this->log->LogError($body);
      return array('CURLERR', curl_error($ch));
    } else {
      $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
    }
    
    // Check for errors
    if($status_code == '200') {
      $this->log->LogResponse($body);          
    } else {
      $this->log->LogError($body);
    }
    return array($status_code, $body);
  }
}
?>