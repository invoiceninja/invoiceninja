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
 * 
 * Amazon Flexible Payments Service
 * 
 */

interface  Amazon_FPS_Interface 
{
    

            
    /**
     * Cancel Token 
     * 
     * Cancels any token installed by the calling application on its own account.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}CancelToken.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_CancelTokenRequest request
     * or Amazon_FPS_Model_CancelTokenRequest object itself
     * @see Amazon_FPS_Model_CancelTokenRequest
     * @return Amazon_FPS_Model_CancelTokenResponse Amazon_FPS_Model_CancelTokenResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancelToken($request);


            
    /**
     * Cancel 
     * 
     * Cancels an ongoing transaction and puts it in cancelled state.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Cancel.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_CancelRequest request
     * or Amazon_FPS_Model_CancelRequest object itself
     * @see Amazon_FPS_Model_CancelRequest
     * @return Amazon_FPS_Model_CancelResponse Amazon_FPS_Model_CancelResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancel($request);


            
    /**
     * Fund Prepaid 
     * 
     * Funds the prepaid balance on the given prepaid instrument.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}FundPrepaid.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_FundPrepaidRequest request
     * or Amazon_FPS_Model_FundPrepaidRequest object itself
     * @see Amazon_FPS_Model_FundPrepaidRequest
     * @return Amazon_FPS_Model_FundPrepaidResponse Amazon_FPS_Model_FundPrepaidResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function fundPrepaid($request);


            
    /**
     * Get Account Activity 
     * 
     * Returns transactions for a given date range.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetAccountActivity.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetAccountActivityRequest request
     * or Amazon_FPS_Model_GetAccountActivityRequest object itself
     * @see Amazon_FPS_Model_GetAccountActivityRequest
     * @return Amazon_FPS_Model_GetAccountActivityResponse Amazon_FPS_Model_GetAccountActivityResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getAccountActivity($request);


            
    /**
     * Get Account Balance 
     * 
     * Returns the account balance for an account in real time.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetAccountBalance.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetAccountBalanceRequest request
     * or Amazon_FPS_Model_GetAccountBalanceRequest object itself
     * @see Amazon_FPS_Model_GetAccountBalanceRequest
     * @return Amazon_FPS_Model_GetAccountBalanceResponse Amazon_FPS_Model_GetAccountBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getAccountBalance($request);


            
    /**
     * Get Debt Balance 
     * 
     * Returns the balance corresponding to the given credit instrument.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetDebtBalance.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetDebtBalanceRequest request
     * or Amazon_FPS_Model_GetDebtBalanceRequest object itself
     * @see Amazon_FPS_Model_GetDebtBalanceRequest
     * @return Amazon_FPS_Model_GetDebtBalanceResponse Amazon_FPS_Model_GetDebtBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getDebtBalance($request);


            
    /**
     * Get Outstanding Debt Balance 
     * 
     * Returns the total outstanding balance for all the credit instruments for the given creditor account.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetOutstandingDebtBalance.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetOutstandingDebtBalanceRequest request
     * or Amazon_FPS_Model_GetOutstandingDebtBalanceRequest object itself
     * @see Amazon_FPS_Model_GetOutstandingDebtBalanceRequest
     * @return Amazon_FPS_Model_GetOutstandingDebtBalanceResponse Amazon_FPS_Model_GetOutstandingDebtBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getOutstandingDebtBalance($request);


            
    /**
     * Get Prepaid Balance 
     * 
     * Returns the balance available on the given prepaid instrument.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetPrepaidBalance.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetPrepaidBalanceRequest request
     * or Amazon_FPS_Model_GetPrepaidBalanceRequest object itself
     * @see Amazon_FPS_Model_GetPrepaidBalanceRequest
     * @return Amazon_FPS_Model_GetPrepaidBalanceResponse Amazon_FPS_Model_GetPrepaidBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getPrepaidBalance($request);


            
    /**
     * Get Token By Caller 
     * 
     * Returns the details of a particular token installed by this calling application using the subway co-branded UI.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetTokenByCaller.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTokenByCallerRequest request
     * or Amazon_FPS_Model_GetTokenByCallerRequest object itself
     * @see Amazon_FPS_Model_GetTokenByCallerRequest
     * @return Amazon_FPS_Model_GetTokenByCallerResponse Amazon_FPS_Model_GetTokenByCallerResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTokenByCaller($request);


            
    /**
     * Cancel Subscription And Refund 
     * 
     * Cancels a subscription.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}CancelSubscriptionAndRefund.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_CancelSubscriptionAndRefundRequest request
     * or Amazon_FPS_Model_CancelSubscriptionAndRefundRequest object itself
     * @see Amazon_FPS_Model_CancelSubscriptionAndRefundRequest
     * @return Amazon_FPS_Model_CancelSubscriptionAndRefundResponse Amazon_FPS_Model_CancelSubscriptionAndRefundResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancelSubscriptionAndRefund($request);


            
    /**
     * Get Token Usage 
     * 
     * Returns the usage of a token.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetTokenUsage.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTokenUsageRequest request
     * or Amazon_FPS_Model_GetTokenUsageRequest object itself
     * @see Amazon_FPS_Model_GetTokenUsageRequest
     * @return Amazon_FPS_Model_GetTokenUsageResponse Amazon_FPS_Model_GetTokenUsageResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTokenUsage($request);


            
    /**
     * Get Tokens 
     * 
     * Returns a list of tokens installed on the given account.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetTokens.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTokensRequest request
     * or Amazon_FPS_Model_GetTokensRequest object itself
     * @see Amazon_FPS_Model_GetTokensRequest
     * @return Amazon_FPS_Model_GetTokensResponse Amazon_FPS_Model_GetTokensResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTokens($request);


            
    /**
     * Get Total Prepaid Liability 
     * 
     * Returns the total liability held by the given account corresponding to all the prepaid instruments owned by the account.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetTotalPrepaidLiability.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTotalPrepaidLiabilityRequest request
     * or Amazon_FPS_Model_GetTotalPrepaidLiabilityRequest object itself
     * @see Amazon_FPS_Model_GetTotalPrepaidLiabilityRequest
     * @return Amazon_FPS_Model_GetTotalPrepaidLiabilityResponse Amazon_FPS_Model_GetTotalPrepaidLiabilityResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTotalPrepaidLiability($request);


            
    /**
     * Get Transaction 
     * 
     * Returns all details of a transaction.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetTransaction.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTransactionRequest request
     * or Amazon_FPS_Model_GetTransactionRequest object itself
     * @see Amazon_FPS_Model_GetTransactionRequest
     * @return Amazon_FPS_Model_GetTransactionResponse Amazon_FPS_Model_GetTransactionResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTransaction($request);


            
    /**
     * Get Transaction Status 
     * 
     * Gets the latest status of a transaction.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetTransactionStatus.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTransactionStatusRequest request
     * or Amazon_FPS_Model_GetTransactionStatusRequest object itself
     * @see Amazon_FPS_Model_GetTransactionStatusRequest
     * @return Amazon_FPS_Model_GetTransactionStatusResponse Amazon_FPS_Model_GetTransactionStatusResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTransactionStatus($request);


            
    /**
     * Get Payment Instruction 
     * 
     * Gets the payment instruction of a token.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}GetPaymentInstruction.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetPaymentInstructionRequest request
     * or Amazon_FPS_Model_GetPaymentInstructionRequest object itself
     * @see Amazon_FPS_Model_GetPaymentInstructionRequest
     * @return Amazon_FPS_Model_GetPaymentInstructionResponse Amazon_FPS_Model_GetPaymentInstructionResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getPaymentInstruction($request);


            
    /**
     * Install Payment Instruction 
     * Installs a payment instruction for caller.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}InstallPaymentInstruction.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_InstallPaymentInstructionRequest request
     * or Amazon_FPS_Model_InstallPaymentInstructionRequest object itself
     * @see Amazon_FPS_Model_InstallPaymentInstructionRequest
     * @return Amazon_FPS_Model_InstallPaymentInstructionResponse Amazon_FPS_Model_InstallPaymentInstructionResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function installPaymentInstruction($request);


            
    /**
     * Pay 
     * 
     * Allows calling applications to move money from a sender to a recipient.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Pay.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_PayRequest request
     * or Amazon_FPS_Model_PayRequest object itself
     * @see Amazon_FPS_Model_PayRequest
     * @return Amazon_FPS_Model_PayResponse Amazon_FPS_Model_PayResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function pay($request);


            
    /**
     * Refund 
     * 
     * Refunds a previously completed transaction.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Refund.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_RefundRequest request
     * or Amazon_FPS_Model_RefundRequest object itself
     * @see Amazon_FPS_Model_RefundRequest
     * @return Amazon_FPS_Model_RefundResponse Amazon_FPS_Model_RefundResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function refund($request);


            
    /**
     * Reserve 
     * 
     * Reserve API is part of the Reserve and Settle API conjunction that serve the purpose of a pay where the authorization and settlement have a timing 				difference.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Reserve.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_ReserveRequest request
     * or Amazon_FPS_Model_ReserveRequest object itself
     * @see Amazon_FPS_Model_ReserveRequest
     * @return Amazon_FPS_Model_ReserveResponse Amazon_FPS_Model_ReserveResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function reserve($request);


            
    /**
     * Settle 
     * 
     * The Settle API is used in conjunction with the Reserve API and is used to settle previously reserved transaction.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}Settle.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_SettleRequest request
     * or Amazon_FPS_Model_SettleRequest object itself
     * @see Amazon_FPS_Model_SettleRequest
     * @return Amazon_FPS_Model_SettleResponse Amazon_FPS_Model_SettleResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function settle($request);


            
    /**
     * Settle Debt 
     * 
     * Allows a caller to initiate a transaction that atomically transfers money from a sender’s payment instrument to the recipient, while decreasing corresponding 				debt balance.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}SettleDebt.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_SettleDebtRequest request
     * or Amazon_FPS_Model_SettleDebtRequest object itself
     * @see Amazon_FPS_Model_SettleDebtRequest
     * @return Amazon_FPS_Model_SettleDebtResponse Amazon_FPS_Model_SettleDebtResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function settleDebt($request);


            
    /**
     * Write Off Debt 
     * 
     * Allows a creditor to write off the debt balance accumulated partially or fully at any time.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}WriteOffDebt.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_WriteOffDebtRequest request
     * or Amazon_FPS_Model_WriteOffDebtRequest object itself
     * @see Amazon_FPS_Model_WriteOffDebtRequest
     * @return Amazon_FPS_Model_WriteOffDebtResponse Amazon_FPS_Model_WriteOffDebtResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function writeOffDebt($request);


            
    /**
     * Verify Signature 
     * 
     * Verify the signature that FPS sent in IPN or callback urls.
     *   
     * @see http://docs.amazonwebservices.com/${docPath}VerifySignature.html      
     * @param mixed $request array of parameters for Amazon_FPS_Model_VerifySignatureRequest request
     * or Amazon_FPS_Model_VerifySignatureRequest object itself
     * @see Amazon_FPS_Model_VerifySignatureRequest
     * @return Amazon_FPS_Model_VerifySignatureResponse Amazon_FPS_Model_VerifySignatureResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function verifySignature($request);

}