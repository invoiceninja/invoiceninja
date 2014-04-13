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
 * Amazon_FPS_Client is an implementation of Amazon_FPS
 *
 */
class Amazon_FPS_Client implements Amazon_FPS_Interface
{

    const SERVICE_VERSION = '2008-09-17';

    /** @var string */
    private  $_awsAccessKeyId = null;

    /** @var string */
    private  $_awsSecretAccessKey = null;

    /** @var array */
    private  $_config = array ('ServiceURL' => 'https://fps.amazonaws.com',
                               'UserAgent' => 'Amazon FPS PHP5 Library',
                               'SignatureVersion' => 2,
                               'SignatureMethod' => 'HmacSHA256',
                               'ProxyHost' => null,
                               'ProxyPort' => -1,
                               'MaxErrorRetry' => 3
                               );

    /**
     * Construct new Client
     *
     * @param string $awsAccessKeyId AWS Access Key ID
     * @param string $awsSecretAccessKey AWS Secret Access Key
     * @param array $config configuration options.
     * Valid configuration options are:
     * <ul>
     * <li>ServiceURL</li>
     * <li>UserAgent</li>
     * <li>SignatureVersion</li>
     * <li>TimesRetryOnError</li>
     * <li>ProxyHost</li>
     * <li>ProxyPort</li>
     * <li>MaxErrorRetry</li>
     * </ul>
     */
    public function __construct($awsAccessKeyId, $awsSecretAccessKey, $config = null)
    {
        iconv_set_encoding('output_encoding', 'UTF-8');
        iconv_set_encoding('input_encoding', 'UTF-8');
        iconv_set_encoding('internal_encoding', 'UTF-8');

        $this->_awsAccessKeyId = $awsAccessKeyId;
        $this->_awsSecretAccessKey = $awsSecretAccessKey;
        if (!is_null($config)) $this->_config = array_merge($this->_config, $config);
    }

    // Public API ------------------------------------------------------------//


            
    /**
     * Cancel Token 
     * 
     * Cancels any token installed by the calling application on its own account.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}CancelToken.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_CancelTokenRequest request
     * or Amazon_FPS_Model_CancelTokenRequest object itself
     * @see Amazon_FPS_Model_CancelToken
     * @return Amazon_FPS_Model_CancelTokenResponse Amazon_FPS_Model_CancelTokenResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancelToken($request)
    {
        if (!$request instanceof Amazon_FPS_Model_CancelTokenRequest) {
            require_once ('Amazon/FPS/Model/CancelTokenRequest.php');
            $request = new Amazon_FPS_Model_CancelTokenRequest($request);
        }
        require_once ('Amazon/FPS/Model/CancelTokenResponse.php');
        return Amazon_FPS_Model_CancelTokenResponse::fromXML($this->_invoke($this->_convertCancelToken($request)));
    }


            
    /**
     * Cancel 
     * 
     * Cancels an ongoing transaction and puts it in cancelled state.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}Cancel.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_CancelRequest request
     * or Amazon_FPS_Model_CancelRequest object itself
     * @see Amazon_FPS_Model_Cancel
     * @return Amazon_FPS_Model_CancelResponse Amazon_FPS_Model_CancelResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancel($request)
    {
        if (!$request instanceof Amazon_FPS_Model_CancelRequest) {
            require_once ('Amazon/FPS/Model/CancelRequest.php');
            $request = new Amazon_FPS_Model_CancelRequest($request);
        }
        require_once ('Amazon/FPS/Model/CancelResponse.php');
        return Amazon_FPS_Model_CancelResponse::fromXML($this->_invoke($this->_convertCancel($request)));
    }


            
    /**
     * Fund Prepaid 
     * 
     * Funds the prepaid balance on the given prepaid instrument.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}FundPrepaid.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_FundPrepaidRequest request
     * or Amazon_FPS_Model_FundPrepaidRequest object itself
     * @see Amazon_FPS_Model_FundPrepaid
     * @return Amazon_FPS_Model_FundPrepaidResponse Amazon_FPS_Model_FundPrepaidResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function fundPrepaid($request)
    {
        if (!$request instanceof Amazon_FPS_Model_FundPrepaidRequest) {
            require_once ('Amazon/FPS/Model/FundPrepaidRequest.php');
            $request = new Amazon_FPS_Model_FundPrepaidRequest($request);
        }
        require_once ('Amazon/FPS/Model/FundPrepaidResponse.php');
        return Amazon_FPS_Model_FundPrepaidResponse::fromXML($this->_invoke($this->_convertFundPrepaid($request)));
    }


            
    /**
     * Get Account Activity 
     * 
     * Returns transactions for a given date range.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetAccountActivity.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetAccountActivityRequest request
     * or Amazon_FPS_Model_GetAccountActivityRequest object itself
     * @see Amazon_FPS_Model_GetAccountActivity
     * @return Amazon_FPS_Model_GetAccountActivityResponse Amazon_FPS_Model_GetAccountActivityResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getAccountActivity($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetAccountActivityRequest) {
            require_once ('Amazon/FPS/Model/GetAccountActivityRequest.php');
            $request = new Amazon_FPS_Model_GetAccountActivityRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetAccountActivityResponse.php');
        return Amazon_FPS_Model_GetAccountActivityResponse::fromXML($this->_invoke($this->_convertGetAccountActivity($request)));
    }


            
    /**
     * Get Account Balance 
     * 
     * Returns the account balance for an account in real time.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetAccountBalance.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetAccountBalanceRequest request
     * or Amazon_FPS_Model_GetAccountBalanceRequest object itself
     * @see Amazon_FPS_Model_GetAccountBalance
     * @return Amazon_FPS_Model_GetAccountBalanceResponse Amazon_FPS_Model_GetAccountBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getAccountBalance($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetAccountBalanceRequest) {
            require_once ('Amazon/FPS/Model/GetAccountBalanceRequest.php');
            $request = new Amazon_FPS_Model_GetAccountBalanceRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetAccountBalanceResponse.php');
        return Amazon_FPS_Model_GetAccountBalanceResponse::fromXML($this->_invoke($this->_convertGetAccountBalance($request)));
    }


            
    /**
     * Get Debt Balance 
     * 
     * Returns the balance corresponding to the given credit instrument.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetDebtBalance.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetDebtBalanceRequest request
     * or Amazon_FPS_Model_GetDebtBalanceRequest object itself
     * @see Amazon_FPS_Model_GetDebtBalance
     * @return Amazon_FPS_Model_GetDebtBalanceResponse Amazon_FPS_Model_GetDebtBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getDebtBalance($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetDebtBalanceRequest) {
            require_once ('Amazon/FPS/Model/GetDebtBalanceRequest.php');
            $request = new Amazon_FPS_Model_GetDebtBalanceRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetDebtBalanceResponse.php');
        return Amazon_FPS_Model_GetDebtBalanceResponse::fromXML($this->_invoke($this->_convertGetDebtBalance($request)));
    }


            
    /**
     * Get Outstanding Debt Balance 
     * 
     * Returns the total outstanding balance for all the credit instruments for the given creditor account.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetOutstandingDebtBalance.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetOutstandingDebtBalanceRequest request
     * or Amazon_FPS_Model_GetOutstandingDebtBalanceRequest object itself
     * @see Amazon_FPS_Model_GetOutstandingDebtBalance
     * @return Amazon_FPS_Model_GetOutstandingDebtBalanceResponse Amazon_FPS_Model_GetOutstandingDebtBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getOutstandingDebtBalance($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetOutstandingDebtBalanceRequest) {
            require_once ('Amazon/FPS/Model/GetOutstandingDebtBalanceRequest.php');
            $request = new Amazon_FPS_Model_GetOutstandingDebtBalanceRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetOutstandingDebtBalanceResponse.php');
        return Amazon_FPS_Model_GetOutstandingDebtBalanceResponse::fromXML($this->_invoke($this->_convertGetOutstandingDebtBalance($request)));
    }


            
    /**
     * Get Prepaid Balance 
     * 
     * Returns the balance available on the given prepaid instrument.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetPrepaidBalance.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetPrepaidBalanceRequest request
     * or Amazon_FPS_Model_GetPrepaidBalanceRequest object itself
     * @see Amazon_FPS_Model_GetPrepaidBalance
     * @return Amazon_FPS_Model_GetPrepaidBalanceResponse Amazon_FPS_Model_GetPrepaidBalanceResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getPrepaidBalance($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetPrepaidBalanceRequest) {
            require_once ('Amazon/FPS/Model/GetPrepaidBalanceRequest.php');
            $request = new Amazon_FPS_Model_GetPrepaidBalanceRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetPrepaidBalanceResponse.php');
        return Amazon_FPS_Model_GetPrepaidBalanceResponse::fromXML($this->_invoke($this->_convertGetPrepaidBalance($request)));
    }


            
    /**
     * Get Token By Caller 
     * 
     * Returns the details of a particular token installed by this calling application using the subway co-branded UI.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetTokenByCaller.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTokenByCallerRequest request
     * or Amazon_FPS_Model_GetTokenByCallerRequest object itself
     * @see Amazon_FPS_Model_GetTokenByCaller
     * @return Amazon_FPS_Model_GetTokenByCallerResponse Amazon_FPS_Model_GetTokenByCallerResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTokenByCaller($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetTokenByCallerRequest) {
            require_once ('Amazon/FPS/Model/GetTokenByCallerRequest.php');
            $request = new Amazon_FPS_Model_GetTokenByCallerRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetTokenByCallerResponse.php');
        return Amazon_FPS_Model_GetTokenByCallerResponse::fromXML($this->_invoke($this->_convertGetTokenByCaller($request)));
    }


            
    /**
     * Cancel Subscription And Refund 
     * 
     * Cancels a subscription.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}CancelSubscriptionAndRefund.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_CancelSubscriptionAndRefundRequest request
     * or Amazon_FPS_Model_CancelSubscriptionAndRefundRequest object itself
     * @see Amazon_FPS_Model_CancelSubscriptionAndRefund
     * @return Amazon_FPS_Model_CancelSubscriptionAndRefundResponse Amazon_FPS_Model_CancelSubscriptionAndRefundResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function cancelSubscriptionAndRefund($request)
    {
        if (!$request instanceof Amazon_FPS_Model_CancelSubscriptionAndRefundRequest) {
            require_once ('Amazon/FPS/Model/CancelSubscriptionAndRefundRequest.php');
            $request = new Amazon_FPS_Model_CancelSubscriptionAndRefundRequest($request);
        }
        require_once ('Amazon/FPS/Model/CancelSubscriptionAndRefundResponse.php');
        return Amazon_FPS_Model_CancelSubscriptionAndRefundResponse::fromXML($this->_invoke($this->_convertCancelSubscriptionAndRefund($request)));
    }


            
    /**
     * Get Token Usage 
     * 
     * Returns the usage of a token.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetTokenUsage.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTokenUsageRequest request
     * or Amazon_FPS_Model_GetTokenUsageRequest object itself
     * @see Amazon_FPS_Model_GetTokenUsage
     * @return Amazon_FPS_Model_GetTokenUsageResponse Amazon_FPS_Model_GetTokenUsageResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTokenUsage($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetTokenUsageRequest) {
            require_once ('Amazon/FPS/Model/GetTokenUsageRequest.php');
            $request = new Amazon_FPS_Model_GetTokenUsageRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetTokenUsageResponse.php');
        return Amazon_FPS_Model_GetTokenUsageResponse::fromXML($this->_invoke($this->_convertGetTokenUsage($request)));
    }


            
    /**
     * Get Tokens 
     * 
     * Returns a list of tokens installed on the given account.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetTokens.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTokensRequest request
     * or Amazon_FPS_Model_GetTokensRequest object itself
     * @see Amazon_FPS_Model_GetTokens
     * @return Amazon_FPS_Model_GetTokensResponse Amazon_FPS_Model_GetTokensResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTokens($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetTokensRequest) {
            require_once ('Amazon/FPS/Model/GetTokensRequest.php');
            $request = new Amazon_FPS_Model_GetTokensRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetTokensResponse.php');
        return Amazon_FPS_Model_GetTokensResponse::fromXML($this->_invoke($this->_convertGetTokens($request)));
    }


            
    /**
     * Get Total Prepaid Liability 
     * 
     * Returns the total liability held by the given account corresponding to all the prepaid instruments owned by the account.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetTotalPrepaidLiability.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTotalPrepaidLiabilityRequest request
     * or Amazon_FPS_Model_GetTotalPrepaidLiabilityRequest object itself
     * @see Amazon_FPS_Model_GetTotalPrepaidLiability
     * @return Amazon_FPS_Model_GetTotalPrepaidLiabilityResponse Amazon_FPS_Model_GetTotalPrepaidLiabilityResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTotalPrepaidLiability($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetTotalPrepaidLiabilityRequest) {
            require_once ('Amazon/FPS/Model/GetTotalPrepaidLiabilityRequest.php');
            $request = new Amazon_FPS_Model_GetTotalPrepaidLiabilityRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetTotalPrepaidLiabilityResponse.php');
        return Amazon_FPS_Model_GetTotalPrepaidLiabilityResponse::fromXML($this->_invoke($this->_convertGetTotalPrepaidLiability($request)));
    }


            
    /**
     * Get Transaction 
     * 
     * Returns all details of a transaction.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetTransaction.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTransactionRequest request
     * or Amazon_FPS_Model_GetTransactionRequest object itself
     * @see Amazon_FPS_Model_GetTransaction
     * @return Amazon_FPS_Model_GetTransactionResponse Amazon_FPS_Model_GetTransactionResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTransaction($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetTransactionRequest) {
            require_once ('Amazon/FPS/Model/GetTransactionRequest.php');
            $request = new Amazon_FPS_Model_GetTransactionRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetTransactionResponse.php');
        return Amazon_FPS_Model_GetTransactionResponse::fromXML($this->_invoke($this->_convertGetTransaction($request)));
    }


            
    /**
     * Get Transaction Status 
     * 
     * Gets the latest status of a transaction.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetTransactionStatus.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetTransactionStatusRequest request
     * or Amazon_FPS_Model_GetTransactionStatusRequest object itself
     * @see Amazon_FPS_Model_GetTransactionStatus
     * @return Amazon_FPS_Model_GetTransactionStatusResponse Amazon_FPS_Model_GetTransactionStatusResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getTransactionStatus($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetTransactionStatusRequest) {
            require_once ('Amazon/FPS/Model/GetTransactionStatusRequest.php');
            $request = new Amazon_FPS_Model_GetTransactionStatusRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetTransactionStatusResponse.php');
        return Amazon_FPS_Model_GetTransactionStatusResponse::fromXML($this->_invoke($this->_convertGetTransactionStatus($request)));
    }


            
    /**
     * Get Payment Instruction 
     * 
     * Gets the payment instruction of a token.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}GetPaymentInstruction.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_GetPaymentInstructionRequest request
     * or Amazon_FPS_Model_GetPaymentInstructionRequest object itself
     * @see Amazon_FPS_Model_GetPaymentInstruction
     * @return Amazon_FPS_Model_GetPaymentInstructionResponse Amazon_FPS_Model_GetPaymentInstructionResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function getPaymentInstruction($request)
    {
        if (!$request instanceof Amazon_FPS_Model_GetPaymentInstructionRequest) {
            require_once ('Amazon/FPS/Model/GetPaymentInstructionRequest.php');
            $request = new Amazon_FPS_Model_GetPaymentInstructionRequest($request);
        }
        require_once ('Amazon/FPS/Model/GetPaymentInstructionResponse.php');
        return Amazon_FPS_Model_GetPaymentInstructionResponse::fromXML($this->_invoke($this->_convertGetPaymentInstruction($request)));
    }


            
    /**
     * Install Payment Instruction 
     * Installs a payment instruction for caller.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}InstallPaymentInstruction.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_InstallPaymentInstructionRequest request
     * or Amazon_FPS_Model_InstallPaymentInstructionRequest object itself
     * @see Amazon_FPS_Model_InstallPaymentInstruction
     * @return Amazon_FPS_Model_InstallPaymentInstructionResponse Amazon_FPS_Model_InstallPaymentInstructionResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function installPaymentInstruction($request)
    {
        if (!$request instanceof Amazon_FPS_Model_InstallPaymentInstructionRequest) {
            require_once ('Amazon/FPS/Model/InstallPaymentInstructionRequest.php');
            $request = new Amazon_FPS_Model_InstallPaymentInstructionRequest($request);
        }
        require_once ('Amazon/FPS/Model/InstallPaymentInstructionResponse.php');
        return Amazon_FPS_Model_InstallPaymentInstructionResponse::fromXML($this->_invoke($this->_convertInstallPaymentInstruction($request)));
    }


            
    /**
     * Pay 
     * 
     * Allows calling applications to move money from a sender to a recipient.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}Pay.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_PayRequest request
     * or Amazon_FPS_Model_PayRequest object itself
     * @see Amazon_FPS_Model_Pay
     * @return Amazon_FPS_Model_PayResponse Amazon_FPS_Model_PayResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function pay($request)
    {
        if (!$request instanceof Amazon_FPS_Model_PayRequest) {
            require_once ('Amazon/FPS/Model/PayRequest.php');
            $request = new Amazon_FPS_Model_PayRequest($request);
        }
        require_once ('Amazon/FPS/Model/PayResponse.php');
        return Amazon_FPS_Model_PayResponse::fromXML($this->_invoke($this->_convertPay($request)));
    }


            
    /**
     * Refund 
     * 
     * Refunds a previously completed transaction.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}Refund.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_RefundRequest request
     * or Amazon_FPS_Model_RefundRequest object itself
     * @see Amazon_FPS_Model_Refund
     * @return Amazon_FPS_Model_RefundResponse Amazon_FPS_Model_RefundResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function refund($request)
    {
        if (!$request instanceof Amazon_FPS_Model_RefundRequest) {
            require_once ('Amazon/FPS/Model/RefundRequest.php');
            $request = new Amazon_FPS_Model_RefundRequest($request);
        }
        require_once ('Amazon/FPS/Model/RefundResponse.php');
        return Amazon_FPS_Model_RefundResponse::fromXML($this->_invoke($this->_convertRefund($request)));
    }


            
    /**
     * Reserve 
     * 
     * Reserve API is part of the Reserve and Settle API conjunction that serve the purpose of a pay where the authorization and settlement have a timing 				difference.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}Reserve.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_ReserveRequest request
     * or Amazon_FPS_Model_ReserveRequest object itself
     * @see Amazon_FPS_Model_Reserve
     * @return Amazon_FPS_Model_ReserveResponse Amazon_FPS_Model_ReserveResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function reserve($request)
    {
        if (!$request instanceof Amazon_FPS_Model_ReserveRequest) {
            require_once ('Amazon/FPS/Model/ReserveRequest.php');
            $request = new Amazon_FPS_Model_ReserveRequest($request);
        }
        require_once ('Amazon/FPS/Model/ReserveResponse.php');
        return Amazon_FPS_Model_ReserveResponse::fromXML($this->_invoke($this->_convertReserve($request)));
    }


            
    /**
     * Settle 
     * 
     * The Settle API is used in conjunction with the Reserve API and is used to settle previously reserved transaction.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}Settle.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_SettleRequest request
     * or Amazon_FPS_Model_SettleRequest object itself
     * @see Amazon_FPS_Model_Settle
     * @return Amazon_FPS_Model_SettleResponse Amazon_FPS_Model_SettleResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function settle($request)
    {
        if (!$request instanceof Amazon_FPS_Model_SettleRequest) {
            require_once ('Amazon/FPS/Model/SettleRequest.php');
            $request = new Amazon_FPS_Model_SettleRequest($request);
        }
        require_once ('Amazon/FPS/Model/SettleResponse.php');
        return Amazon_FPS_Model_SettleResponse::fromXML($this->_invoke($this->_convertSettle($request)));
    }


            
    /**
     * Settle Debt 
     * 
     * Allows a caller to initiate a transaction that atomically transfers money from a sender’s payment instrument to the recipient, while decreasing corresponding 				debt balance.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}SettleDebt.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_SettleDebtRequest request
     * or Amazon_FPS_Model_SettleDebtRequest object itself
     * @see Amazon_FPS_Model_SettleDebt
     * @return Amazon_FPS_Model_SettleDebtResponse Amazon_FPS_Model_SettleDebtResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function settleDebt($request)
    {
        if (!$request instanceof Amazon_FPS_Model_SettleDebtRequest) {
            require_once ('Amazon/FPS/Model/SettleDebtRequest.php');
            $request = new Amazon_FPS_Model_SettleDebtRequest($request);
        }
        require_once ('Amazon/FPS/Model/SettleDebtResponse.php');
        return Amazon_FPS_Model_SettleDebtResponse::fromXML($this->_invoke($this->_convertSettleDebt($request)));
    }


            
    /**
     * Write Off Debt 
     * 
     * Allows a creditor to write off the debt balance accumulated partially or fully at any time.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}WriteOffDebt.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_WriteOffDebtRequest request
     * or Amazon_FPS_Model_WriteOffDebtRequest object itself
     * @see Amazon_FPS_Model_WriteOffDebt
     * @return Amazon_FPS_Model_WriteOffDebtResponse Amazon_FPS_Model_WriteOffDebtResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function writeOffDebt($request)
    {
        if (!$request instanceof Amazon_FPS_Model_WriteOffDebtRequest) {
            require_once ('Amazon/FPS/Model/WriteOffDebtRequest.php');
            $request = new Amazon_FPS_Model_WriteOffDebtRequest($request);
        }
        require_once ('Amazon/FPS/Model/WriteOffDebtResponse.php');
        return Amazon_FPS_Model_WriteOffDebtResponse::fromXML($this->_invoke($this->_convertWriteOffDebt($request)));
    }


            
    /**
     * Verify Signature 
     * 
     * Verify the signature that FPS sent in IPN or callback urls.
     * 
     * @see http://docs.amazonwebservices.com/${docPath}VerifySignature.html
     * @param mixed $request array of parameters for Amazon_FPS_Model_VerifySignatureRequest request
     * or Amazon_FPS_Model_VerifySignatureRequest object itself
     * @see Amazon_FPS_Model_VerifySignature
     * @return Amazon_FPS_Model_VerifySignatureResponse Amazon_FPS_Model_VerifySignatureResponse
     *
     * @throws Amazon_FPS_Exception
     */
    public function verifySignature($request)
    {
        if (!$request instanceof Amazon_FPS_Model_VerifySignatureRequest) {
            require_once ('Amazon/FPS/Model/VerifySignatureRequest.php');
            $request = new Amazon_FPS_Model_VerifySignatureRequest($request);
        }
        require_once ('Amazon/FPS/Model/VerifySignatureResponse.php');
        return Amazon_FPS_Model_VerifySignatureResponse::fromXML($this->_invoke($this->_convertVerifySignature($request)));
    }

        // Private API ------------------------------------------------------------//

    /**
     * Invoke request and return response
     */
    private function _invoke(array $parameters)
    {
        $actionName = $parameters["Action"];
        $response = array();
        $responseBody = null;
        $statusCode = 200;

        /* Submit the request and read response body */
        try {

            /* Add required request parameters */
            $parameters = $this->_addRequiredParameters($parameters);

            $shouldRetry = true;
            $retries = 0;
            do {
                try {
                        $response = $this->_httpPost($parameters);
                        if ($response['Status'] === 200) {
                            $shouldRetry = false;
                        } else {
                            if ($response['Status'] === 500 || $response['Status'] === 503) {
                                $shouldRetry = true;
                                $this->_pauseOnRetry(++$retries, $response['Status']);
                            } else {
                                throw $this->_reportAnyErrors($response['ResponseBody'], $response['Status']);
                            }
                       }
                /* Rethrow on deserializer error */
                } catch (Exception $e) {
                    require_once ('Amazon/FPS/Exception.php');
                    if ($e instanceof Amazon_FPS_Exception) {
                        throw $e;
                    } else {
                        require_once ('Amazon/FPS/Exception.php');
                        throw new Amazon_FPS_Exception(array('Exception' => $e, 'Message' => $e->getMessage()));
                    }
                }

            } while ($shouldRetry);

        } catch (Amazon_FPS_Exception $se) {
            throw $se;
        } catch (Exception $t) {
            throw new Amazon_FPS_Exception(array('Exception' => $t, 'Message' => $t->getMessage()));
        }

        return $response['ResponseBody'];
    }

    /**
     * Look for additional error strings in the response and return formatted exception
     */
    private function _reportAnyErrors($responseBody, $status, Exception $e =  null)
    {
        $ex = null;
        if (!is_null($responseBody) && strpos($responseBody, '<') === 0) {
            if (preg_match('@<RequestId>(.*)</RequestId>.*<Error><Code>(.*)</Code><Message>(.*)</Message></Error>.*(<Error>)?@mi',
                $responseBody, $errorMatcherOne)) {

                $requestId = $errorMatcherOne[1];
                $code = $errorMatcherOne[2];
                $message = $errorMatcherOne[3];

                require_once ('Amazon/FPS/Exception.php');
                $ex = new Amazon_FPS_Exception(array ('Message' => $message, 'StatusCode' => $status, 'ErrorCode' => $code,
                                                           'ErrorType' => 'Unknown', 'RequestId' => $requestId, 'XML' => $responseBody));

            } elseif (preg_match('@<Error><Code>(.*)</Code><Message>(.*)</Message></Error>.*(<Error>)?.*<RequestID>(.*)</RequestID>@mi',
                $responseBody, $errorMatcherTwo)) {

                $code = $errorMatcherTwo[1];
                $message = $errorMatcherTwo[2];
                $requestId = $errorMatcherTwo[4];
                require_once ('Amazon/FPS/Exception.php');
                $ex = new Amazon_FPS_Exception(array ('Message' => $message, 'StatusCode' => $status, 'ErrorCode' => $code,
                                                              'ErrorType' => 'Unknown', 'RequestId' => $requestId, 'XML' => $responseBody));
            } elseif (preg_match('@<Error><Type>(.*)</Type><Code>(.*)</Code><Message>(.*)</Message>.*</Error>.*(<Error>)?.*<RequestId>(.*)</RequestId>@mi',
                $responseBody, $errorMatcherThree)) {

                $type = $errorMatcherThree[1];
                $code = $errorMatcherThree[2];
                $message = $errorMatcherThree[3];
                $requestId = $errorMatcherThree[5];
                require_once ('Amazon/FPS/Exception.php');
                $ex = new Amazon_FPS_Exception(array ('Message' => $message, 'StatusCode' => $status, 'ErrorCode' => $code,
                                                              'ErrorType' => $type, 'RequestId' => $requestId, 'XML' => $responseBody));

            } else {
                require_once ('Amazon/FPS/Exception.php');
                $ex = new Amazon_FPS_Exception(array('Message' => 'Internal Error', 'StatusCode' => $status));
            }
        } else {
            require_once ('Amazon/FPS/Exception.php');
            $ex = new Amazon_FPS_Exception(array('Message' => 'Internal Error', 'StatusCode' => $status));
        }
        return $ex;
    }



    /**
     * Perform HTTP post with exponential retries on error 500 and 503
     *
     */
    private function _httpPost(array $parameters)
    {

        $query = $this->_getParametersAsString($parameters);
        $url = parse_url ($this->_config['ServiceURL']);
        $post  = "POST / HTTP/1.0\r\n";
        $post .= "Host: " . $url['host'] . "\r\n";
        $post .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n";
        $post .= "Content-Length: " . strlen($query) . "\r\n";
        $post .= "User-Agent: " . $this->_config['UserAgent'] . "\r\n";
        $post .= "\r\n";
        $post .= $query;
        $port = array_key_exists('port',$url) ? $url['port'] : null;
        $scheme = '';

        switch ($url['scheme']) {
            case 'https':
                $scheme = 'ssl://';
                $port = $port === null ? 443 : $port;
                break;
            default:
                $scheme = '';
                $port = $port === null ? 80 : $port;
        }

        $response = '';
        if ($socket = @fsockopen($scheme . $url['host'], $port, $errno, $errstr, 10)) {

            fwrite($socket, $post);

            while (!feof($socket)) {
                $response .= fgets($socket, 1160);
            }
            fclose($socket);

            list($other, $responseBody) = explode("\r\n\r\n", $response, 2);
            $other = preg_split("/\r\n|\n|\r/", $other);
            list($protocol, $code, $text) = explode(' ', trim(array_shift($other)), 3);
        } else {
            throw new Exception ("Unable to establish connection to host " . $url['host'] . " $errstr");
        }


        return array ('Status' => (int)$code, 'ResponseBody' => $responseBody);
    }

    /**
     * Exponential sleep on failed request
     * @param retries current retry
     * @throws Amazon_FPS_Exception if maximum number of retries has been reached
     */
    private function _pauseOnRetry($retries, $status)
    {
        if ($retries <= $this->_config['MaxErrorRetry']) {
            $delay = (int) (pow(4, $retries) * 100000) ;
            usleep($delay);
        } else {
            require_once ('Amazon/FPS/Exception.php');
            throw new Amazon_FPS_Exception (array ('Message' => "Maximum number of retry attempts reached :  $retries", 'StatusCode' => $status));
        }
    }

    /**
     * Add authentication related and version parameters
     */
    private function _addRequiredParameters(array $parameters)
    {
        $parameters['AWSAccessKeyId'] = $this->_awsAccessKeyId;
        $parameters['Timestamp'] = $this->_getFormattedTimestamp();
        $parameters['Version'] = self::SERVICE_VERSION;
        $parameters['SignatureVersion'] = $this->_config['SignatureVersion'];
        if ($parameters['SignatureVersion'] > 1) {
            $parameters['SignatureMethod'] = $this->_config['SignatureMethod'];
        }
        $parameters['Signature'] = $this->_signParameters($parameters, $this->_awsSecretAccessKey);

        return $parameters;
    }

    /**
     * Convert paremeters to Url encoded query string
     */
    private function _getParametersAsString(array $parameters)
    {
        $queryParameters = array();
        foreach ($parameters as $key => $value) {
            $queryParameters[] = $key . '=' . $this->_urlencode($value);
        }
        return implode('&', $queryParameters);
    }


    /**
     * Computes RFC 2104-compliant HMAC signature for request parameters
     * Implements AWS Signature, as per following spec:
     *
     * If Signature Version is 0, it signs concatenated Action and Timestamp
     *
     * If Signature Version is 1, it performs the following:
     *
     * Sorts all  parameters (including SignatureVersion and excluding Signature,
     * the value of which is being created), ignoring case.
     *
     * Iterate over the sorted list and append the parameter name (in original case)
     * and then its value. It will not URL-encode the parameter values before
     * constructing this string. There are no separators.
     *
     * If Signature Version is 2, string to sign is based on following:
     *
     *    1. The HTTP Request Method followed by an ASCII newline (%0A)
     *    2. The HTTP Host header in the form of lowercase host, followed by an ASCII newline.
     *    3. The URL encoded HTTP absolute path component of the URI
     *       (up to but not including the query string parameters);
     *       if this is empty use a forward '/'. This parameter is followed by an ASCII newline.
     *    4. The concatenation of all query string components (names and values)
     *       as UTF-8 characters which are URL encoded as per RFC 3986
     *       (hex characters MUST be uppercase), sorted using lexicographic byte ordering.
     *       Parameter names are separated from their values by the '=' character
     *       (ASCII character 61), even if the value is empty.
     *       Pairs of parameter and values are separated by the '&' character (ASCII code 38).
     *
     */
    private function _signParameters(array $parameters, $key) {
        $signatureVersion = $parameters['SignatureVersion'];
        $algorithm = "HmacSHA1";
        $stringToSign = null;
        if (0 === $signatureVersion) {
            $stringToSign = $this->_calculateStringToSignV0($parameters);
        } else if (1 === $signatureVersion) {
            $stringToSign = $this->_calculateStringToSignV1($parameters);
        } else if (2 === $signatureVersion) {
            $algorithm = $this->_config['SignatureMethod'];
            $parameters['SignatureMethod'] = $algorithm;
            $stringToSign = $this->_calculateStringToSignV2($parameters);
        } else {
            throw new Exception("Invalid Signature Version specified");
        }
        return $this->_sign($stringToSign, $key, $algorithm);
    }

    /**
     * Calculate String to Sign for SignatureVersion 0
     * @param array $parameters request parameters
     * @return String to Sign
     */
    private function _calculateStringToSignV0(array $parameters) {
        return $parameters['Action'] .  $parameters['Timestamp'];
    }

    /**
     * Calculate String to Sign for SignatureVersion 1
     * @param array $parameters request parameters
     * @return String to Sign
     */
    private function _calculateStringToSignV1(array $parameters) {
        $data = '';
        uksort($parameters, 'strcasecmp');
        foreach ($parameters as $parameterName => $parameterValue) {
            $data .= $parameterName . $parameterValue;
        }
        return $data;
    }

    /**
     * Calculate String to Sign for SignatureVersion 2
     * @param array $parameters request parameters
     * @return String to Sign
     */
    private function _calculateStringToSignV2(array $parameters) {
        $data = 'POST';
        $data .= "\n";
        $endpoint = parse_url ($this->_config['ServiceURL']);
        $data .= $endpoint['host'];
        $data .= "\n";
        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;
        if (!isset ($uri)) {
        	$uri = "/";
        }
		$uriencoded = implode("/", array_map(array($this, "_urlencode"), explode("/", $uri)));
        $data .= $uriencoded;
        $data .= "\n";
        uksort($parameters, 'strcmp');
        $data .= $this->_getParametersAsString($parameters);
        return $data;
    }

    private function _urlencode($value) {
		return str_replace('%7E', '~', rawurlencode($value));
    }


    /**
     * Computes RFC 2104-compliant HMAC signature.
     */
    private function _sign($data, $key, $algorithm)
    {
        if ($algorithm === 'HmacSHA1') {
            $hash = 'sha1';
        } else if ($algorithm === 'HmacSHA256') {
            $hash = 'sha256';
        } else {
            throw new Exception ("Non-supported signing method specified");
        }
        return base64_encode(
            hash_hmac($hash, $data, $key, true)
        );
    }


    /**
     * Formats date as ISO 8601 timestamp
     */
    private function _getFormattedTimestamp()
    {
        return gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time());
    }


                        
    /**
     * Convert CancelRequest to name value pairs
     */
    private function _convertCancel($request) {
        
        $parameters = array();
        $parameters['Action'] = 'Cancel';
        if ($request->isSetTransactionId()) {
            $parameters['TransactionId'] =  $request->getTransactionId();
        }
        if ($request->isSetDescription()) {
            $parameters['Description'] =  $request->getDescription();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert CancelTokenRequest to name value pairs
     */
    private function _convertCancelToken($request) {
        
        $parameters = array();
        $parameters['Action'] = 'CancelToken';
        if ($request->isSetTokenId()) {
            $parameters['TokenId'] =  $request->getTokenId();
        }
        if ($request->isSetReasonText()) {
            $parameters['ReasonText'] =  $request->getReasonText();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert CancelSubscriptionAndRefundRequest to name value pairs
     */
    private function _convertCancelSubscriptionAndRefund($request) {
        
        $parameters = array();
        $parameters['Action'] = 'CancelSubscriptionAndRefund';
        if ($request->isSetSubscriptionId()) {
            $parameters['SubscriptionId'] =  $request->getSubscriptionId();
        }
        if ($request->isSetRefundAmount()) {
            $refundAmountcancelSubscriptionAndRefundRequest = $request->getRefundAmount();
            if ($refundAmountcancelSubscriptionAndRefundRequest->isSetCurrencyCode()) {
                $parameters['RefundAmount' . '.' . 'CurrencyCode'] =  $refundAmountcancelSubscriptionAndRefundRequest->getCurrencyCode();
            }
            if ($refundAmountcancelSubscriptionAndRefundRequest->isSetValue()) {
                $parameters['RefundAmount' . '.' . 'Value'] =  $refundAmountcancelSubscriptionAndRefundRequest->getValue();
            }
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetCancelReason()) {
            $parameters['CancelReason'] =  $request->getCancelReason();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert FundPrepaidRequest to name value pairs
     */
    private function _convertFundPrepaid($request) {
        
        $parameters = array();
        $parameters['Action'] = 'FundPrepaid';
        if ($request->isSetSenderTokenId()) {
            $parameters['SenderTokenId'] =  $request->getSenderTokenId();
        }
        if ($request->isSetPrepaidInstrumentId()) {
            $parameters['PrepaidInstrumentId'] =  $request->getPrepaidInstrumentId();
        }
        if ($request->isSetFundingAmount()) {
            $fundingAmountfundPrepaidRequest = $request->getFundingAmount();
            if ($fundingAmountfundPrepaidRequest->isSetCurrencyCode()) {
                $parameters['FundingAmount' . '.' . 'CurrencyCode'] =  $fundingAmountfundPrepaidRequest->getCurrencyCode();
            }
            if ($fundingAmountfundPrepaidRequest->isSetValue()) {
                $parameters['FundingAmount' . '.' . 'Value'] =  $fundingAmountfundPrepaidRequest->getValue();
            }
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetSenderDescription()) {
            $parameters['SenderDescription'] =  $request->getSenderDescription();
        }
        if ($request->isSetCallerDescription()) {
            $parameters['CallerDescription'] =  $request->getCallerDescription();
        }
        if ($request->isSetDescriptorPolicy()) {
            $descriptorPolicyfundPrepaidRequest = $request->getDescriptorPolicy();
            if ($descriptorPolicyfundPrepaidRequest->isSetSoftDescriptorType()) {
                $parameters['DescriptorPolicy' . '.' . 'SoftDescriptorType'] =  $descriptorPolicyfundPrepaidRequest->getSoftDescriptorType();
            }
            if ($descriptorPolicyfundPrepaidRequest->isSetCSOwner()) {
                $parameters['DescriptorPolicy' . '.' . 'CSOwner'] =  $descriptorPolicyfundPrepaidRequest->getCSOwner();
            }
        }
        if ($request->isSetTransactionTimeoutInMins()) {
            $parameters['TransactionTimeoutInMins'] =  $request->getTransactionTimeoutInMins();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetAccountActivityRequest to name value pairs
     */
    private function _convertGetAccountActivity($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetAccountActivity';
        if ($request->isSetMaxBatchSize()) {
            $parameters['MaxBatchSize'] =  $request->getMaxBatchSize();
        }
        if ($request->isSetStartDate()) {
            $parameters['StartDate'] =  $request->getStartDate();
        }
        if ($request->isSetEndDate()) {
            $parameters['EndDate'] =  $request->getEndDate();
        }
        if ($request->isSetSortOrderByDate()) {
            $parameters['SortOrderByDate'] =  $request->getSortOrderByDate();
        }
        if ($request->isSetFPSOperation()) {
            $parameters['FPSOperation'] =  $request->getFPSOperation();
        }
        if ($request->isSetPaymentMethod()) {
            $parameters['PaymentMethod'] =  $request->getPaymentMethod();
        }
        foreach  ($request->getRole() as $rolegetAccountActivityRequestIndex => $rolegetAccountActivityRequest) {
            $parameters['Role' . '.'  . ($rolegetAccountActivityRequestIndex + 1)] =  $rolegetAccountActivityRequest;
        }
        if ($request->isSetTransactionStatus()) {
            $parameters['TransactionStatus'] =  $request->getTransactionStatus();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetAccountBalanceRequest to name value pairs
     */
    private function _convertGetAccountBalance($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetAccountBalance';

        return $parameters;
    }
        
                                        
    /**
     * Convert GetDebtBalanceRequest to name value pairs
     */
    private function _convertGetDebtBalance($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetDebtBalance';
        if ($request->isSetCreditInstrumentId()) {
            $parameters['CreditInstrumentId'] =  $request->getCreditInstrumentId();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetOutstandingDebtBalanceRequest to name value pairs
     */
    private function _convertGetOutstandingDebtBalance($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetOutstandingDebtBalance';

        return $parameters;
    }
        
                                        
    /**
     * Convert GetPrepaidBalanceRequest to name value pairs
     */
    private function _convertGetPrepaidBalance($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetPrepaidBalance';
        if ($request->isSetPrepaidInstrumentId()) {
            $parameters['PrepaidInstrumentId'] =  $request->getPrepaidInstrumentId();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetTokenByCallerRequest to name value pairs
     */
    private function _convertGetTokenByCaller($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetTokenByCaller';
        if ($request->isSetTokenId()) {
            $parameters['TokenId'] =  $request->getTokenId();
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetTokenUsageRequest to name value pairs
     */
    private function _convertGetTokenUsage($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetTokenUsage';
        if ($request->isSetTokenId()) {
            $parameters['TokenId'] =  $request->getTokenId();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetTokensRequest to name value pairs
     */
    private function _convertGetTokens($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetTokens';
        if ($request->isSetTokenStatus()) {
            $parameters['TokenStatus'] =  $request->getTokenStatus();
        }
        if ($request->isSetTokenType()) {
            $parameters['TokenType'] =  $request->getTokenType();
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetTokenFriendlyName()) {
            $parameters['TokenFriendlyName'] =  $request->getTokenFriendlyName();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetTotalPrepaidLiabilityRequest to name value pairs
     */
    private function _convertGetTotalPrepaidLiability($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetTotalPrepaidLiability';

        return $parameters;
    }
        
                                        
    /**
     * Convert GetTransactionRequest to name value pairs
     */
    private function _convertGetTransaction($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetTransaction';
        if ($request->isSetTransactionId()) {
            $parameters['TransactionId'] =  $request->getTransactionId();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetTransactionStatusRequest to name value pairs
     */
    private function _convertGetTransactionStatus($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetTransactionStatus';
        if ($request->isSetTransactionId()) {
            $parameters['TransactionId'] =  $request->getTransactionId();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert GetPaymentInstructionRequest to name value pairs
     */
    private function _convertGetPaymentInstruction($request) {
        
        $parameters = array();
        $parameters['Action'] = 'GetPaymentInstruction';
        if ($request->isSetTokenId()) {
            $parameters['TokenId'] =  $request->getTokenId();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert InstallPaymentInstructionRequest to name value pairs
     */
    private function _convertInstallPaymentInstruction($request) {
        
        $parameters = array();
        $parameters['Action'] = 'InstallPaymentInstruction';
        if ($request->isSetPaymentInstruction()) {
            $parameters['PaymentInstruction'] =  $request->getPaymentInstruction();
        }
        if ($request->isSetTokenFriendlyName()) {
            $parameters['TokenFriendlyName'] =  $request->getTokenFriendlyName();
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetTokenType()) {
            $parameters['TokenType'] =  $request->getTokenType();
        }
        if ($request->isSetPaymentReason()) {
            $parameters['PaymentReason'] =  $request->getPaymentReason();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert PayRequest to name value pairs
     */
    private function _convertPay($request) {
        
        $parameters = array();
        $parameters['Action'] = 'Pay';
        if ($request->isSetSenderTokenId()) {
            $parameters['SenderTokenId'] =  $request->getSenderTokenId();
        }
        if ($request->isSetRecipientTokenId()) {
            $parameters['RecipientTokenId'] =  $request->getRecipientTokenId();
        }
        if ($request->isSetTransactionAmount()) {
            $transactionAmountpayRequest = $request->getTransactionAmount();
            if ($transactionAmountpayRequest->isSetCurrencyCode()) {
                $parameters['TransactionAmount' . '.' . 'CurrencyCode'] =  $transactionAmountpayRequest->getCurrencyCode();
            }
            if ($transactionAmountpayRequest->isSetValue()) {
                $parameters['TransactionAmount' . '.' . 'Value'] =  $transactionAmountpayRequest->getValue();
            }
        }
        if ($request->isSetChargeFeeTo()) {
            $parameters['ChargeFeeTo'] =  $request->getChargeFeeTo();
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetCallerDescription()) {
            $parameters['CallerDescription'] =  $request->getCallerDescription();
        }
        if ($request->isSetSenderDescription()) {
            $parameters['SenderDescription'] =  $request->getSenderDescription();
        }
        if ($request->isSetDescriptorPolicy()) {
            $descriptorPolicypayRequest = $request->getDescriptorPolicy();
            if ($descriptorPolicypayRequest->isSetSoftDescriptorType()) {
                $parameters['DescriptorPolicy' . '.' . 'SoftDescriptorType'] =  $descriptorPolicypayRequest->getSoftDescriptorType();
            }
            if ($descriptorPolicypayRequest->isSetCSOwner()) {
                $parameters['DescriptorPolicy' . '.' . 'CSOwner'] =  $descriptorPolicypayRequest->getCSOwner();
            }
        }
        if ($request->isSetTransactionTimeoutInMins()) {
            $parameters['TransactionTimeoutInMins'] =  $request->getTransactionTimeoutInMins();
        }
        if ($request->isSetMarketplaceFixedFee()) {
            $marketplaceFixedFeepayRequest = $request->getMarketplaceFixedFee();
            if ($marketplaceFixedFeepayRequest->isSetCurrencyCode()) {
                $parameters['MarketplaceFixedFee' . '.' . 'CurrencyCode'] =  $marketplaceFixedFeepayRequest->getCurrencyCode();
            }
            if ($marketplaceFixedFeepayRequest->isSetValue()) {
                $parameters['MarketplaceFixedFee' . '.' . 'Value'] =  $marketplaceFixedFeepayRequest->getValue();
            }
        }
        if ($request->isSetMarketplaceVariableFee()) {
            $parameters['MarketplaceVariableFee'] =  $request->getMarketplaceVariableFee();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert RefundRequest to name value pairs
     */
    private function _convertRefund($request) {
        
        $parameters = array();
        $parameters['Action'] = 'Refund';
        if ($request->isSetTransactionId()) {
            $parameters['TransactionId'] =  $request->getTransactionId();
        }
        if ($request->isSetRefundAmount()) {
            $refundAmountrefundRequest = $request->getRefundAmount();
            if ($refundAmountrefundRequest->isSetCurrencyCode()) {
                $parameters['RefundAmount' . '.' . 'CurrencyCode'] =  $refundAmountrefundRequest->getCurrencyCode();
            }
            if ($refundAmountrefundRequest->isSetValue()) {
                $parameters['RefundAmount' . '.' . 'Value'] =  $refundAmountrefundRequest->getValue();
            }
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetCallerDescription()) {
            $parameters['CallerDescription'] =  $request->getCallerDescription();
        }
        if ($request->isSetMarketplaceRefundPolicy()) {
            $parameters['MarketplaceRefundPolicy'] =  $request->getMarketplaceRefundPolicy();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert ReserveRequest to name value pairs
     */
    private function _convertReserve($request) {
        
        $parameters = array();
        $parameters['Action'] = 'Reserve';
        if ($request->isSetSenderTokenId()) {
            $parameters['SenderTokenId'] =  $request->getSenderTokenId();
        }
        if ($request->isSetRecipientTokenId()) {
            $parameters['RecipientTokenId'] =  $request->getRecipientTokenId();
        }
        if ($request->isSetTransactionAmount()) {
            $transactionAmountreserveRequest = $request->getTransactionAmount();
            if ($transactionAmountreserveRequest->isSetCurrencyCode()) {
                $parameters['TransactionAmount' . '.' . 'CurrencyCode'] =  $transactionAmountreserveRequest->getCurrencyCode();
            }
            if ($transactionAmountreserveRequest->isSetValue()) {
                $parameters['TransactionAmount' . '.' . 'Value'] =  $transactionAmountreserveRequest->getValue();
            }
        }
        if ($request->isSetChargeFeeTo()) {
            $parameters['ChargeFeeTo'] =  $request->getChargeFeeTo();
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetCallerDescription()) {
            $parameters['CallerDescription'] =  $request->getCallerDescription();
        }
        if ($request->isSetSenderDescription()) {
            $parameters['SenderDescription'] =  $request->getSenderDescription();
        }
        if ($request->isSetDescriptorPolicy()) {
            $descriptorPolicyreserveRequest = $request->getDescriptorPolicy();
            if ($descriptorPolicyreserveRequest->isSetSoftDescriptorType()) {
                $parameters['DescriptorPolicy' . '.' . 'SoftDescriptorType'] =  $descriptorPolicyreserveRequest->getSoftDescriptorType();
            }
            if ($descriptorPolicyreserveRequest->isSetCSOwner()) {
                $parameters['DescriptorPolicy' . '.' . 'CSOwner'] =  $descriptorPolicyreserveRequest->getCSOwner();
            }
        }
        if ($request->isSetTransactionTimeoutInMins()) {
            $parameters['TransactionTimeoutInMins'] =  $request->getTransactionTimeoutInMins();
        }
        if ($request->isSetMarketplaceFixedFee()) {
            $marketplaceFixedFeereserveRequest = $request->getMarketplaceFixedFee();
            if ($marketplaceFixedFeereserveRequest->isSetCurrencyCode()) {
                $parameters['MarketplaceFixedFee' . '.' . 'CurrencyCode'] =  $marketplaceFixedFeereserveRequest->getCurrencyCode();
            }
            if ($marketplaceFixedFeereserveRequest->isSetValue()) {
                $parameters['MarketplaceFixedFee' . '.' . 'Value'] =  $marketplaceFixedFeereserveRequest->getValue();
            }
        }
        if ($request->isSetMarketplaceVariableFee()) {
            $parameters['MarketplaceVariableFee'] =  $request->getMarketplaceVariableFee();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert SettleRequest to name value pairs
     */
    private function _convertSettle($request) {
        
        $parameters = array();
        $parameters['Action'] = 'Settle';
        if ($request->isSetReserveTransactionId()) {
            $parameters['ReserveTransactionId'] =  $request->getReserveTransactionId();
        }
        if ($request->isSetTransactionAmount()) {
            $transactionAmountsettleRequest = $request->getTransactionAmount();
            if ($transactionAmountsettleRequest->isSetCurrencyCode()) {
                $parameters['TransactionAmount' . '.' . 'CurrencyCode'] =  $transactionAmountsettleRequest->getCurrencyCode();
            }
            if ($transactionAmountsettleRequest->isSetValue()) {
                $parameters['TransactionAmount' . '.' . 'Value'] =  $transactionAmountsettleRequest->getValue();
            }
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert SettleDebtRequest to name value pairs
     */
    private function _convertSettleDebt($request) {
        
        $parameters = array();
        $parameters['Action'] = 'SettleDebt';
        if ($request->isSetSenderTokenId()) {
            $parameters['SenderTokenId'] =  $request->getSenderTokenId();
        }
        if ($request->isSetCreditInstrumentId()) {
            $parameters['CreditInstrumentId'] =  $request->getCreditInstrumentId();
        }
        if ($request->isSetSettlementAmount()) {
            $settlementAmountsettleDebtRequest = $request->getSettlementAmount();
            if ($settlementAmountsettleDebtRequest->isSetCurrencyCode()) {
                $parameters['SettlementAmount' . '.' . 'CurrencyCode'] =  $settlementAmountsettleDebtRequest->getCurrencyCode();
            }
            if ($settlementAmountsettleDebtRequest->isSetValue()) {
                $parameters['SettlementAmount' . '.' . 'Value'] =  $settlementAmountsettleDebtRequest->getValue();
            }
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetSenderDescription()) {
            $parameters['SenderDescription'] =  $request->getSenderDescription();
        }
        if ($request->isSetCallerDescription()) {
            $parameters['CallerDescription'] =  $request->getCallerDescription();
        }
        if ($request->isSetDescriptorPolicy()) {
            $descriptorPolicysettleDebtRequest = $request->getDescriptorPolicy();
            if ($descriptorPolicysettleDebtRequest->isSetSoftDescriptorType()) {
                $parameters['DescriptorPolicy' . '.' . 'SoftDescriptorType'] =  $descriptorPolicysettleDebtRequest->getSoftDescriptorType();
            }
            if ($descriptorPolicysettleDebtRequest->isSetCSOwner()) {
                $parameters['DescriptorPolicy' . '.' . 'CSOwner'] =  $descriptorPolicysettleDebtRequest->getCSOwner();
            }
        }
        if ($request->isSetTransactionTimeoutInMins()) {
            $parameters['TransactionTimeoutInMins'] =  $request->getTransactionTimeoutInMins();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert WriteOffDebtRequest to name value pairs
     */
    private function _convertWriteOffDebt($request) {
        
        $parameters = array();
        $parameters['Action'] = 'WriteOffDebt';
        if ($request->isSetCreditInstrumentId()) {
            $parameters['CreditInstrumentId'] =  $request->getCreditInstrumentId();
        }
        if ($request->isSetAdjustmentAmount()) {
            $adjustmentAmountwriteOffDebtRequest = $request->getAdjustmentAmount();
            if ($adjustmentAmountwriteOffDebtRequest->isSetCurrencyCode()) {
                $parameters['AdjustmentAmount' . '.' . 'CurrencyCode'] =  $adjustmentAmountwriteOffDebtRequest->getCurrencyCode();
            }
            if ($adjustmentAmountwriteOffDebtRequest->isSetValue()) {
                $parameters['AdjustmentAmount' . '.' . 'Value'] =  $adjustmentAmountwriteOffDebtRequest->getValue();
            }
        }
        if ($request->isSetCallerReference()) {
            $parameters['CallerReference'] =  $request->getCallerReference();
        }
        if ($request->isSetCallerDescription()) {
            $parameters['CallerDescription'] =  $request->getCallerDescription();
        }

        return $parameters;
    }
        
                                        
    /**
     * Convert VerifySignatureRequest to name value pairs
     */
    private function _convertVerifySignature($request) {
        
        $parameters = array();
        $parameters['Action'] = 'VerifySignature';
        if ($request->isSetUrlEndPoint()) {
            $parameters['UrlEndPoint'] =  $request->getUrlEndPoint();
        }
        if ($request->isSetHttpParameters()) {
            $parameters['HttpParameters'] =  $request->getHttpParameters();
        }

        return $parameters;
    }
        
                                                                                                                                                                                                                                                                                                                                                        
}