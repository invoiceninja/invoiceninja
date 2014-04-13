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
/******************************************************************************* 
 *    __  _    _  ___ 
 *   (  )( \/\/ )/ __)
 *   /__\ \    / \__ \
 *  (_)(_) \/\/  (___/
 * 
 *  Amazon FPS PHP5 Library
 *  Generated: Wed Sep 23 03:35:04 PDT 2009
 * 
 */

/**
 *  @see Amazon_FPS_Interface
 */
require_once ('Amazon/FPS/Interface.php'); 

/**
 * 
 * Amazon Flexible Payments Service
 * 
 */
class  Amazon_FPS_Mock implements Amazon_FPS_Interface
{
    // Public API ------------------------------------------------------------//


            
    /**
     * Cancel 
     * 
     * Cancels an ongoing transaction and puts it in cancelled state.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Cancel.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_Cancel request or Amazon_FPS_Model_Cancel object itself
     * @see Amazon_FPS_Model_Cancel
     * @return Amazon_FPS_Model_CancelResponse Amazon_FPS_Model_CancelResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancel($request) 
    {
        require_once ('Amazon/FPS/Model/CancelResponse.php');
        return Amazon_FPS_Model_CancelResponse::fromXML($this->_invoke('Cancel'));
    }


            
            
    /**
     * Cancel Subscription And Refund 
     * 
     * Cancels a subscription.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}CancelSubscriptionAndRefund.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_CancelSubscriptionAndRefund request or Amazon_FPS_Model_CancelSubscriptionAndRefund object itself
     * @see Amazon_FPS_Model_CancelSubscriptionAndRefund
     * @return Amazon_FPS_Model_CancelSubscriptionAndRefundResponse Amazon_FPS_Model_CancelSubscriptionAndRefundResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancelSubscriptionAndRefund($request) 
    {
        require_once ('Amazon/FPS/Model/CancelSubscriptionAndRefundResponse.php');
        return Amazon_FPS_Model_CancelSubscriptionAndRefundResponse::fromXML($this->_invoke('CancelSubscriptionAndRefund'));
    }


            
    /**
     * Get Transaction Status 
     * 
     * Gets the latest status of a transaction.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetTransactionStatus.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTransactionStatus request or Amazon_FPS_Model_GetTransactionStatus object itself
     * @see Amazon_FPS_Model_GetTransactionStatus
     * @return Amazon_FPS_Model_GetTransactionStatusResponse Amazon_FPS_Model_GetTransactionStatusResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTransactionStatus($request) 
    {
        require_once ('Amazon/FPS/Model/GetTransactionStatusResponse.php');
        return Amazon_FPS_Model_GetTransactionStatusResponse::fromXML($this->_invoke('GetTransactionStatus'));
    }


            
    /**
     * Refund 
     * 
     * Refunds a previously completed transaction.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Refund.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_Refund request or Amazon_FPS_Model_Refund object itself
     * @see Amazon_FPS_Model_Refund
     * @return Amazon_FPS_Model_RefundResponse Amazon_FPS_Model_RefundResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function refund($request) 
    {
        require_once ('Amazon/FPS/Model/RefundResponse.php');
        return Amazon_FPS_Model_RefundResponse::fromXML($this->_invoke('Refund'));
    }



            
    /**
     * Settle 
     * 
     * The Settle API is used in conjunction with the Reserve API and is used to settle previously reserved transaction.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Settle.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_Settle request or Amazon_FPS_Model_Settle object itself
     * @see Amazon_FPS_Model_Settle
     * @return Amazon_FPS_Model_SettleResponse Amazon_FPS_Model_SettleResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function settle($request) 
    {
        require_once ('Amazon/FPS/Model/SettleResponse.php');
        return Amazon_FPS_Model_SettleResponse::fromXML($this->_invoke('Settle'));
    }



            
    /**
     * Verify Signature 
     * 
     * Verify the signature that FPS sent in IPN or callback urls.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}VerifySignature.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_VerifySignature request or Amazon_FPS_Model_VerifySignature object itself
     * @see Amazon_FPS_Model_VerifySignature
     * @return Amazon_FPS_Model_VerifySignatureResponse Amazon_FPS_Model_VerifySignatureResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function verifySignature($request) 
    {
        require_once ('Amazon/FPS/Model/VerifySignatureResponse.php');
        return Amazon_FPS_Model_VerifySignatureResponse::fromXML($this->_invoke('VerifySignature'));
    }

    // Private API ------------------------------------------------------------//

    private function _invoke($actionName)
    {
        return $xml = file_get_contents('Amazon/FPS/Mock/' . $actionName . 'Response.xml', /** search include path */ TRUE);
    }
}
