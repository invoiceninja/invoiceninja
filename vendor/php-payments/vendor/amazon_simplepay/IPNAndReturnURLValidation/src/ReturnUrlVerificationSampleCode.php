<?php
/** 
 *  PHP Version 5
 *
 *  @category    Amazon
 *  @package     Amazon_FPS
 *  @copyright   Copyright 2008-2010 Amazon Technologies, Inc.
 *  @link        http://aws.amazon.com
 *  @license     http://aws.amazon.com/apache2.0  Apache License, Version 2.0
 *  @version     2008-09-17
 */
require_once '.config.inc.php';
require_once 'SignatureUtilsForOutbound.php';
  
class ReturnUrlVerificationSampleCode {

	public static function test() {
        $utils = new SignatureUtilsForOutbound();
        
        //Parameters present in return url.
        $params["transactionId"] = "14GPH3CZ83RPQ1ZH6J2G85NL1IO3KO8641R";
        $params["transactionDate"] = "1254987247";
        $params["status"] = "PS";
        $params["signatureMethod"] = "RSA-SHA1";
        $params["signatureVersion"] = "2";
        $params["buyerEmail"] = "test-sender@amazon.com";
        $params["recipientEmail"] = "test-recipient@amazon.com";
        $params["operation"] = "pay";
        $params["transactionAmount"] = "USD 1.1";
        $params["referenceId"] = "test-reference123";
        $params["buyerName"] = "test sender";
        $params["recipientName"] = "Test Business";
        $params["paymentMethod"] = "Credit Card";
        $params["paymentReason"] = "Test Widget";
        $params["certificateUrl"] = "https://fps.sandbox.amazonaws.com/certs/090909/PKICert.pem";
        $params["signature"] = "VirmnCtqA/A+s+H+SE7Oj8Ku7Lfay6OKkJgP/Q0hyQeaR6evI8Usokg698utW6xzJsiUudXm0KpmqiWM33o1aby3AOxZqWUC//aMZPO9vdw1NWR5fOJ++8AR9BAfcUtTHWc2QOHa1UyJalqeMsHuQj2IqQCMmOAUHPFkHhwAZMS9Ifkkxjqczg4S0vK9FoO39rFYkReYdL9SvuFyj6byAnqd3D7i/lgw+6jXjAlM9MqYiisMLyCGk0IQsrux5VbiQgI9LiGqUThGh7o2XkEFWvmPlKFmdQVnLxN9RNOK4pwrktbjgrBfVKZu1BBBXjfwwy9xzin0Kw5uNlCD2ReoZA==";
 
        $urlEndPoint = "http://yourwebsite.com/return.jsp"; //Your return url end point. 
        print "Verifying return url signed using signature v2 ....\n";
        //return url is sent as a http GET request and hence we specify GET as the http method.
        //Signature verification does not require your secret key
        print "Is signature correct: " . $utils->validateRequest($params, $urlEndPoint, "GET") . "\n";
	}
}

ReturnUrlVerificationSampleCode::test(); 
?>
