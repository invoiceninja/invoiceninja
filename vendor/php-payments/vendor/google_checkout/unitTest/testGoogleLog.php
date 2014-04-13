<?php

require_once('classes/phpunit.php');
require_once('../library/googlelog.php');

define('API_CALLBACK_ERROR_LOG', 'error.log');
define('API_CALLBACK_MESSAGE_LOG', 'message.log');


class TestGoogleLog extends TestCase {          
  function TestGoogleLog($name) {
    $this->TestCase($name);
  }

  function setUp() {
    /* put any common setup here */
  }

  function tearDown() {
    /* put any common endup here */
  }

  function testGoogleLogFilesExist(){
    $Glog = new GoogleLog(API_CALLBACK_ERROR_LOG, API_CALLBACK_MESSAGE_LOG, L_ALL, false);
    $this->assertEquals($Glog->logLevel, L_ALL, "This Should Pass");
  }
  
  function testGoogleLogError(){
    $Glog = new GoogleLog(API_CALLBACK_ERROR_LOG, API_CALLBACK_MESSAGE_LOG, L_ERR, false);
    $this->assert($Glog->LogError("error"));
  }

  function testGoogleLogRequest(){
    $Glog = new GoogleLog(API_CALLBACK_ERROR_LOG, API_CALLBACK_MESSAGE_LOG, L_RQST, false);
    $this->assert($Glog->LogRequest("request"));
  }

  function testGoogleLogResponse(){
    $Glog = new GoogleLog(API_CALLBACK_ERROR_LOG, API_CALLBACK_MESSAGE_LOG, L_RESP, false);
    $this->assert($Glog->LogResponse("response"));
  }
}

if(!isset($suite)) {
  $suite = new TestSuite();
}

$suite->addTest(new TestGoogleLog("testGoogleLogFilesExist"));
$suite->addTest(new TestGoogleLog("testGoogleLogError"));
$suite->addTest(new TestGoogleLog("testGoogleLogRequest"));
$suite->addTest(new TestGoogleLog("testGoogleLogResponse"));

?>