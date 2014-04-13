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

require_once 'ButtonGenerator.php';
 
class StandardButtonSample {

	private static $accessKey = "<insert-your-access-key-here>";				//Put your Access Key here
	private static $secretKey = "<insert-your-secret-key-here>";			//Put  your Secret Key here
	private static $amount="USD 1.1"; 						//Enter the amount you want to collect for the item
	private static $signatureMethod="HmacSHA256"; 					// Valid values  are  HmacSHA256 and HmacSHA1.
	private static $description="Test Widget";					 //Enter a description of the item
	private static $referenceId="test-reference123"; 				 //Optionally, enter an ID that uniquely identifies this transaction for your records
	private static $abandonUrl="http://yourwebsite.com/cancel.html";		 //Optionally, enter the URL where senders should be redirected if they cancel their transaction
	private static $returnUrl="http://yourwebsite.com/return.html";			 //Optionally enter the URL where buyers should be redirected after they complete the transaction
	private static $immediateReturn="0"; 						 //Optionally, enter "1" if you want to skip the final status page in Amazon Payments
	private static $processImmediate="1"; 						 //Optionally, enter "1" if you want to settle the transaction immediately else "0". Default value is "1" 
	private static $ipnUrl="http://yourwebsite.com/ipn";				 //Optionally, type the URL of your host page to which Amazon Payments should send the IPN transaction information.
	private static $collectShippingAddress=null;					 //Optionally, enter "1" if you want Amazon Payments to return the buyer's shipping address as part of the transaction information
	 private static $environment="sandbox"; 					//Valid values are "sandbox" or "prod"

	public static function Sampleform() {
		try{
			ButtonGenerator::GenerateForm(self::$accessKey,self::$secretKey,self::$amount, self::$description, self::$referenceId, self::$immediateReturn,self::$returnUrl, self::$abandonUrl, self::$processImmediate, self::$ipnUrl, self::$collectShippingAddress,self::$signatureMethod, self::$environment);

		}
		catch(Exception $e){
			echo 'Exception : ', $e->getMessage(),"\n";
		}
	}
}
	StandardButtonSample::SampleForm();

?>
