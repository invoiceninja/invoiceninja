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
  
class IPNVerificationSampleCode {

	public static function test() {
		
        $utils = new SignatureUtilsForOutbound();
        
        //Parameters present in ipn.
	$params["transactionId"] = "14GPH3CZ83RPQ1ZH6J2G85NL1IO3KO8641R"; 
	$params["transactionDate"] = "1254987247"; 
	$params["status"] = "PS"; 
	$params["signatureMethod"] = "RSA-SHA1"; 
	$params["signatureVersion"] = "2"; 
	$params["buyerEmail"] = "test-sender@amazon.com"; 
	$params["recipientEmail"] = "test-recipient@amazon.com"; 
	$params["operation"] = "pay"; 
	$params["transactionAmount"] = "USD 1.100000"; 
	$params["referenceId"] = "test-reference123"; 
	$params["buyerName"] = "test sender"; 
	$params["recipientName"] = "Test Business"; 
	$params["paymentMethod"] = "CC"; 
	$params["paymentReason"] = "Test Widget"; 
	$params["certificateUrl"] = "https://fps.sandbox.amazonaws.com/certs/090909/PKICert.pem"; 
	$params["signature"] ="g2tEn6VVu8VKsxnkWeCPn8M9HABkzkVGbYTozSSKg9Y7B5Xsvq5GSoXkDlaz+izQM56wzvgFCou79un06KI6CeE4lf0SSsonoPInqvTrKoS/XPZqBChtdfciCqSyWBpPZ2YaEbSYEZdk1YZW0W7oeezgQqgzBL/CLN9U128GyFllt3/Yxr6p+XBltBUjh0kGmdAFVuFgwYq7h7cyMwAyseIRU7vDW5qsTreAPBmae9h3v4oZly5CyNDP+4HhExyzakf2r+UBEqj9EwZtek3k9qj956dlG8Dd3QeEF9AqjLp0D+7MyZr0rupNcWNbO1wGX8aEda/FvoWMRxXB3sU9dw=="; 

        $urlEndPoint = "http://yourwebsite.com/ipn.jsp"; //Your url end point receiving the ipn.
         
        print "Verifying IPN signed using signature v2 ....\n";
        //IPN is sent as a http POST request and hence we specify POST as the http method.
        //Signature verification does not require your secret key
        print "Is signature correct: " . $utils->validateRequest($params, $urlEndPoint, "POST") . "\n";
	}
}

IPNVerificationSampleCode::test(); 
?>
