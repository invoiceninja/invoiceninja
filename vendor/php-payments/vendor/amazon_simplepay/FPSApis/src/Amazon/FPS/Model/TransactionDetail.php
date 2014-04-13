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
 *  @see Amazon_FPS_Model
 */
require_once ('Amazon/FPS/Model.php');  

    

/**
 * Amazon_FPS_Model_TransactionDetail
 * 
 * Properties:
 * <ul>
 * 
 * <li>TransactionId: string</li>
 * <li>CallerReference: string</li>
 * <li>CallerDescription: string</li>
 * <li>SenderDescription: string</li>
 * <li>DateReceived: string</li>
 * <li>DateCompleted: string</li>
 * <li>TransactionAmount: Amazon_FPS_Model_Amount</li>
 * <li>FPSFees: Amazon_FPS_Model_Amount</li>
 * <li>MarketplaceFees: Amazon_FPS_Model_Amount</li>
 * <li>FPSFeesPaidBy: string</li>
 * <li>SenderTokenId: string</li>
 * <li>RecipientTokenId: string</li>
 * <li>PrepaidInstrumentId: string</li>
 * <li>CreditInstrumentId: string</li>
 * <li>FPSOperation: string</li>
 * <li>PaymentMethod: string</li>
 * <li>TransactionStatus: string</li>
 * <li>StatusCode: string</li>
 * <li>StatusMessage: string</li>
 * <li>SenderName: string</li>
 * <li>SenderEmail: string</li>
 * <li>CallerName: string</li>
 * <li>RecipientName: string</li>
 * <li>RecipientEmail: string</li>
 * <li>RelatedTransaction: Amazon_FPS_Model_RelatedTransaction</li>
 * <li>StatusHistory: Amazon_FPS_Model_StatusHistory</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_TransactionDetail extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_TransactionDetail
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TransactionId: string</li>
     * <li>CallerReference: string</li>
     * <li>CallerDescription: string</li>
     * <li>SenderDescription: string</li>
     * <li>DateReceived: string</li>
     * <li>DateCompleted: string</li>
     * <li>TransactionAmount: Amazon_FPS_Model_Amount</li>
     * <li>FPSFees: Amazon_FPS_Model_Amount</li>
     * <li>MarketplaceFees: Amazon_FPS_Model_Amount</li>
     * <li>FPSFeesPaidBy: string</li>
     * <li>SenderTokenId: string</li>
     * <li>RecipientTokenId: string</li>
     * <li>PrepaidInstrumentId: string</li>
     * <li>CreditInstrumentId: string</li>
     * <li>FPSOperation: string</li>
     * <li>PaymentMethod: string</li>
     * <li>TransactionStatus: string</li>
     * <li>StatusCode: string</li>
     * <li>StatusMessage: string</li>
     * <li>SenderName: string</li>
     * <li>SenderEmail: string</li>
     * <li>CallerName: string</li>
     * <li>RecipientName: string</li>
     * <li>RecipientEmail: string</li>
     * <li>RelatedTransaction: Amazon_FPS_Model_RelatedTransaction</li>
     * <li>StatusHistory: Amazon_FPS_Model_StatusHistory</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerReference' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerDescription' => array('FieldValue' => null, 'FieldType' => 'string'),
        'SenderDescription' => array('FieldValue' => null, 'FieldType' => 'string'),
        'DateReceived' => array('FieldValue' => null, 'FieldType' => 'string'),
        'DateCompleted' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionAmount' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'FPSFees' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'MarketplaceFees' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'FPSFeesPaidBy' => array('FieldValue' => null, 'FieldType' => 'string'),
        'SenderTokenId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RecipientTokenId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'PrepaidInstrumentId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CreditInstrumentId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'FPSOperation' => array('FieldValue' => null, 'FieldType' => 'string'),
        'PaymentMethod' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionStatus' => array('FieldValue' => null, 'FieldType' => 'string'),
        'StatusCode' => array('FieldValue' => null, 'FieldType' => 'string'),
        'StatusMessage' => array('FieldValue' => null, 'FieldType' => 'string'),
        'SenderName' => array('FieldValue' => null, 'FieldType' => 'string'),
        'SenderEmail' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerName' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RecipientName' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RecipientEmail' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RelatedTransaction' => array('FieldValue' => array(), 'FieldType' => array('Amazon_FPS_Model_RelatedTransaction')),
        'StatusHistory' => array('FieldValue' => array(), 'FieldType' => array('Amazon_FPS_Model_StatusHistory')),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the TransactionId property.
     * 
     * @return string TransactionId
     */
    public function getTransactionId() 
    {
        return $this->_fields['TransactionId']['FieldValue'];
    }

    /**
     * Sets the value of the TransactionId property.
     * 
     * @param string TransactionId
     * @return this instance
     */
    public function setTransactionId($value) 
    {
        $this->_fields['TransactionId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the TransactionId and returns this instance
     * 
     * @param string $value TransactionId
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withTransactionId($value)
    {
        $this->setTransactionId($value);
        return $this;
    }


    /**
     * Checks if TransactionId is set
     * 
     * @return bool true if TransactionId  is set
     */
    public function isSetTransactionId()
    {
        return !is_null($this->_fields['TransactionId']['FieldValue']);
    }

    /**
     * Gets the value of the CallerReference property.
     * 
     * @return string CallerReference
     */
    public function getCallerReference() 
    {
        return $this->_fields['CallerReference']['FieldValue'];
    }

    /**
     * Sets the value of the CallerReference property.
     * 
     * @param string CallerReference
     * @return this instance
     */
    public function setCallerReference($value) 
    {
        $this->_fields['CallerReference']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the CallerReference and returns this instance
     * 
     * @param string $value CallerReference
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withCallerReference($value)
    {
        $this->setCallerReference($value);
        return $this;
    }


    /**
     * Checks if CallerReference is set
     * 
     * @return bool true if CallerReference  is set
     */
    public function isSetCallerReference()
    {
        return !is_null($this->_fields['CallerReference']['FieldValue']);
    }

    /**
     * Gets the value of the CallerDescription property.
     * 
     * @return string CallerDescription
     */
    public function getCallerDescription() 
    {
        return $this->_fields['CallerDescription']['FieldValue'];
    }

    /**
     * Sets the value of the CallerDescription property.
     * 
     * @param string CallerDescription
     * @return this instance
     */
    public function setCallerDescription($value) 
    {
        $this->_fields['CallerDescription']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the CallerDescription and returns this instance
     * 
     * @param string $value CallerDescription
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withCallerDescription($value)
    {
        $this->setCallerDescription($value);
        return $this;
    }


    /**
     * Checks if CallerDescription is set
     * 
     * @return bool true if CallerDescription  is set
     */
    public function isSetCallerDescription()
    {
        return !is_null($this->_fields['CallerDescription']['FieldValue']);
    }

    /**
     * Gets the value of the SenderDescription property.
     * 
     * @return string SenderDescription
     */
    public function getSenderDescription() 
    {
        return $this->_fields['SenderDescription']['FieldValue'];
    }

    /**
     * Sets the value of the SenderDescription property.
     * 
     * @param string SenderDescription
     * @return this instance
     */
    public function setSenderDescription($value) 
    {
        $this->_fields['SenderDescription']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the SenderDescription and returns this instance
     * 
     * @param string $value SenderDescription
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withSenderDescription($value)
    {
        $this->setSenderDescription($value);
        return $this;
    }


    /**
     * Checks if SenderDescription is set
     * 
     * @return bool true if SenderDescription  is set
     */
    public function isSetSenderDescription()
    {
        return !is_null($this->_fields['SenderDescription']['FieldValue']);
    }

    /**
     * Gets the value of the DateReceived property.
     * 
     * @return string DateReceived
     */
    public function getDateReceived() 
    {
        return $this->_fields['DateReceived']['FieldValue'];
    }

    /**
     * Sets the value of the DateReceived property.
     * 
     * @param string DateReceived
     * @return this instance
     */
    public function setDateReceived($value) 
    {
        $this->_fields['DateReceived']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the DateReceived and returns this instance
     * 
     * @param string $value DateReceived
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withDateReceived($value)
    {
        $this->setDateReceived($value);
        return $this;
    }


    /**
     * Checks if DateReceived is set
     * 
     * @return bool true if DateReceived  is set
     */
    public function isSetDateReceived()
    {
        return !is_null($this->_fields['DateReceived']['FieldValue']);
    }

    /**
     * Gets the value of the DateCompleted property.
     * 
     * @return string DateCompleted
     */
    public function getDateCompleted() 
    {
        return $this->_fields['DateCompleted']['FieldValue'];
    }

    /**
     * Sets the value of the DateCompleted property.
     * 
     * @param string DateCompleted
     * @return this instance
     */
    public function setDateCompleted($value) 
    {
        $this->_fields['DateCompleted']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the DateCompleted and returns this instance
     * 
     * @param string $value DateCompleted
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withDateCompleted($value)
    {
        $this->setDateCompleted($value);
        return $this;
    }


    /**
     * Checks if DateCompleted is set
     * 
     * @return bool true if DateCompleted  is set
     */
    public function isSetDateCompleted()
    {
        return !is_null($this->_fields['DateCompleted']['FieldValue']);
    }

    /**
     * Gets the value of the TransactionAmount.
     * 
     * @return Amount TransactionAmount
     */
    public function getTransactionAmount() 
    {
        return $this->_fields['TransactionAmount']['FieldValue'];
    }

    /**
     * Sets the value of the TransactionAmount.
     * 
     * @param Amount TransactionAmount
     * @return void
     */
    public function setTransactionAmount($value) 
    {
        $this->_fields['TransactionAmount']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the TransactionAmount  and returns this instance
     * 
     * @param Amount $value TransactionAmount
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withTransactionAmount($value)
    {
        $this->setTransactionAmount($value);
        return $this;
    }


    /**
     * Checks if TransactionAmount  is set
     * 
     * @return bool true if TransactionAmount property is set
     */
    public function isSetTransactionAmount()
    {
        return !is_null($this->_fields['TransactionAmount']['FieldValue']);

    }

    /**
     * Gets the value of the FPSFees.
     * 
     * @return Amount FPSFees
     */
    public function getFPSFees() 
    {
        return $this->_fields['FPSFees']['FieldValue'];
    }

    /**
     * Sets the value of the FPSFees.
     * 
     * @param Amount FPSFees
     * @return void
     */
    public function setFPSFees($value) 
    {
        $this->_fields['FPSFees']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the FPSFees  and returns this instance
     * 
     * @param Amount $value FPSFees
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withFPSFees($value)
    {
        $this->setFPSFees($value);
        return $this;
    }


    /**
     * Checks if FPSFees  is set
     * 
     * @return bool true if FPSFees property is set
     */
    public function isSetFPSFees()
    {
        return !is_null($this->_fields['FPSFees']['FieldValue']);

    }

    /**
     * Gets the value of the MarketplaceFees.
     * 
     * @return Amount MarketplaceFees
     */
    public function getMarketplaceFees() 
    {
        return $this->_fields['MarketplaceFees']['FieldValue'];
    }

    /**
     * Sets the value of the MarketplaceFees.
     * 
     * @param Amount MarketplaceFees
     * @return void
     */
    public function setMarketplaceFees($value) 
    {
        $this->_fields['MarketplaceFees']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the MarketplaceFees  and returns this instance
     * 
     * @param Amount $value MarketplaceFees
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withMarketplaceFees($value)
    {
        $this->setMarketplaceFees($value);
        return $this;
    }


    /**
     * Checks if MarketplaceFees  is set
     * 
     * @return bool true if MarketplaceFees property is set
     */
    public function isSetMarketplaceFees()
    {
        return !is_null($this->_fields['MarketplaceFees']['FieldValue']);

    }

    /**
     * Gets the value of the FPSFeesPaidBy property.
     * 
     * @return string FPSFeesPaidBy
     */
    public function getFPSFeesPaidBy() 
    {
        return $this->_fields['FPSFeesPaidBy']['FieldValue'];
    }

    /**
     * Sets the value of the FPSFeesPaidBy property.
     * 
     * @param string FPSFeesPaidBy
     * @return this instance
     */
    public function setFPSFeesPaidBy($value) 
    {
        $this->_fields['FPSFeesPaidBy']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the FPSFeesPaidBy and returns this instance
     * 
     * @param string $value FPSFeesPaidBy
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withFPSFeesPaidBy($value)
    {
        $this->setFPSFeesPaidBy($value);
        return $this;
    }


    /**
     * Checks if FPSFeesPaidBy is set
     * 
     * @return bool true if FPSFeesPaidBy  is set
     */
    public function isSetFPSFeesPaidBy()
    {
        return !is_null($this->_fields['FPSFeesPaidBy']['FieldValue']);
    }

    /**
     * Gets the value of the SenderTokenId property.
     * 
     * @return string SenderTokenId
     */
    public function getSenderTokenId() 
    {
        return $this->_fields['SenderTokenId']['FieldValue'];
    }

    /**
     * Sets the value of the SenderTokenId property.
     * 
     * @param string SenderTokenId
     * @return this instance
     */
    public function setSenderTokenId($value) 
    {
        $this->_fields['SenderTokenId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the SenderTokenId and returns this instance
     * 
     * @param string $value SenderTokenId
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withSenderTokenId($value)
    {
        $this->setSenderTokenId($value);
        return $this;
    }


    /**
     * Checks if SenderTokenId is set
     * 
     * @return bool true if SenderTokenId  is set
     */
    public function isSetSenderTokenId()
    {
        return !is_null($this->_fields['SenderTokenId']['FieldValue']);
    }

    /**
     * Gets the value of the RecipientTokenId property.
     * 
     * @return string RecipientTokenId
     */
    public function getRecipientTokenId() 
    {
        return $this->_fields['RecipientTokenId']['FieldValue'];
    }

    /**
     * Sets the value of the RecipientTokenId property.
     * 
     * @param string RecipientTokenId
     * @return this instance
     */
    public function setRecipientTokenId($value) 
    {
        $this->_fields['RecipientTokenId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the RecipientTokenId and returns this instance
     * 
     * @param string $value RecipientTokenId
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withRecipientTokenId($value)
    {
        $this->setRecipientTokenId($value);
        return $this;
    }


    /**
     * Checks if RecipientTokenId is set
     * 
     * @return bool true if RecipientTokenId  is set
     */
    public function isSetRecipientTokenId()
    {
        return !is_null($this->_fields['RecipientTokenId']['FieldValue']);
    }

    /**
     * Gets the value of the PrepaidInstrumentId property.
     * 
     * @return string PrepaidInstrumentId
     */
    public function getPrepaidInstrumentId() 
    {
        return $this->_fields['PrepaidInstrumentId']['FieldValue'];
    }

    /**
     * Sets the value of the PrepaidInstrumentId property.
     * 
     * @param string PrepaidInstrumentId
     * @return this instance
     */
    public function setPrepaidInstrumentId($value) 
    {
        $this->_fields['PrepaidInstrumentId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the PrepaidInstrumentId and returns this instance
     * 
     * @param string $value PrepaidInstrumentId
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withPrepaidInstrumentId($value)
    {
        $this->setPrepaidInstrumentId($value);
        return $this;
    }


    /**
     * Checks if PrepaidInstrumentId is set
     * 
     * @return bool true if PrepaidInstrumentId  is set
     */
    public function isSetPrepaidInstrumentId()
    {
        return !is_null($this->_fields['PrepaidInstrumentId']['FieldValue']);
    }

    /**
     * Gets the value of the CreditInstrumentId property.
     * 
     * @return string CreditInstrumentId
     */
    public function getCreditInstrumentId() 
    {
        return $this->_fields['CreditInstrumentId']['FieldValue'];
    }

    /**
     * Sets the value of the CreditInstrumentId property.
     * 
     * @param string CreditInstrumentId
     * @return this instance
     */
    public function setCreditInstrumentId($value) 
    {
        $this->_fields['CreditInstrumentId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the CreditInstrumentId and returns this instance
     * 
     * @param string $value CreditInstrumentId
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withCreditInstrumentId($value)
    {
        $this->setCreditInstrumentId($value);
        return $this;
    }


    /**
     * Checks if CreditInstrumentId is set
     * 
     * @return bool true if CreditInstrumentId  is set
     */
    public function isSetCreditInstrumentId()
    {
        return !is_null($this->_fields['CreditInstrumentId']['FieldValue']);
    }

    /**
     * Gets the value of the FPSOperation property.
     * 
     * @return string FPSOperation
     */
    public function getFPSOperation() 
    {
        return $this->_fields['FPSOperation']['FieldValue'];
    }

    /**
     * Sets the value of the FPSOperation property.
     * 
     * @param string FPSOperation
     * @return this instance
     */
    public function setFPSOperation($value) 
    {
        $this->_fields['FPSOperation']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the FPSOperation and returns this instance
     * 
     * @param string $value FPSOperation
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withFPSOperation($value)
    {
        $this->setFPSOperation($value);
        return $this;
    }


    /**
     * Checks if FPSOperation is set
     * 
     * @return bool true if FPSOperation  is set
     */
    public function isSetFPSOperation()
    {
        return !is_null($this->_fields['FPSOperation']['FieldValue']);
    }

    /**
     * Gets the value of the PaymentMethod property.
     * 
     * @return string PaymentMethod
     */
    public function getPaymentMethod() 
    {
        return $this->_fields['PaymentMethod']['FieldValue'];
    }

    /**
     * Sets the value of the PaymentMethod property.
     * 
     * @param string PaymentMethod
     * @return this instance
     */
    public function setPaymentMethod($value) 
    {
        $this->_fields['PaymentMethod']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the PaymentMethod and returns this instance
     * 
     * @param string $value PaymentMethod
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withPaymentMethod($value)
    {
        $this->setPaymentMethod($value);
        return $this;
    }


    /**
     * Checks if PaymentMethod is set
     * 
     * @return bool true if PaymentMethod  is set
     */
    public function isSetPaymentMethod()
    {
        return !is_null($this->_fields['PaymentMethod']['FieldValue']);
    }

    /**
     * Gets the value of the TransactionStatus property.
     * 
     * @return string TransactionStatus
     */
    public function getTransactionStatus() 
    {
        return $this->_fields['TransactionStatus']['FieldValue'];
    }

    /**
     * Sets the value of the TransactionStatus property.
     * 
     * @param string TransactionStatus
     * @return this instance
     */
    public function setTransactionStatus($value) 
    {
        $this->_fields['TransactionStatus']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the TransactionStatus and returns this instance
     * 
     * @param string $value TransactionStatus
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withTransactionStatus($value)
    {
        $this->setTransactionStatus($value);
        return $this;
    }


    /**
     * Checks if TransactionStatus is set
     * 
     * @return bool true if TransactionStatus  is set
     */
    public function isSetTransactionStatus()
    {
        return !is_null($this->_fields['TransactionStatus']['FieldValue']);
    }

    /**
     * Gets the value of the StatusCode property.
     * 
     * @return string StatusCode
     */
    public function getStatusCode() 
    {
        return $this->_fields['StatusCode']['FieldValue'];
    }

    /**
     * Sets the value of the StatusCode property.
     * 
     * @param string StatusCode
     * @return this instance
     */
    public function setStatusCode($value) 
    {
        $this->_fields['StatusCode']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the StatusCode and returns this instance
     * 
     * @param string $value StatusCode
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withStatusCode($value)
    {
        $this->setStatusCode($value);
        return $this;
    }


    /**
     * Checks if StatusCode is set
     * 
     * @return bool true if StatusCode  is set
     */
    public function isSetStatusCode()
    {
        return !is_null($this->_fields['StatusCode']['FieldValue']);
    }

    /**
     * Gets the value of the StatusMessage property.
     * 
     * @return string StatusMessage
     */
    public function getStatusMessage() 
    {
        return $this->_fields['StatusMessage']['FieldValue'];
    }

    /**
     * Sets the value of the StatusMessage property.
     * 
     * @param string StatusMessage
     * @return this instance
     */
    public function setStatusMessage($value) 
    {
        $this->_fields['StatusMessage']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the StatusMessage and returns this instance
     * 
     * @param string $value StatusMessage
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withStatusMessage($value)
    {
        $this->setStatusMessage($value);
        return $this;
    }


    /**
     * Checks if StatusMessage is set
     * 
     * @return bool true if StatusMessage  is set
     */
    public function isSetStatusMessage()
    {
        return !is_null($this->_fields['StatusMessage']['FieldValue']);
    }

    /**
     * Gets the value of the SenderName property.
     * 
     * @return string SenderName
     */
    public function getSenderName() 
    {
        return $this->_fields['SenderName']['FieldValue'];
    }

    /**
     * Sets the value of the SenderName property.
     * 
     * @param string SenderName
     * @return this instance
     */
    public function setSenderName($value) 
    {
        $this->_fields['SenderName']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the SenderName and returns this instance
     * 
     * @param string $value SenderName
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withSenderName($value)
    {
        $this->setSenderName($value);
        return $this;
    }


    /**
     * Checks if SenderName is set
     * 
     * @return bool true if SenderName  is set
     */
    public function isSetSenderName()
    {
        return !is_null($this->_fields['SenderName']['FieldValue']);
    }

    /**
     * Gets the value of the SenderEmail property.
     * 
     * @return string SenderEmail
     */
    public function getSenderEmail() 
    {
        return $this->_fields['SenderEmail']['FieldValue'];
    }

    /**
     * Sets the value of the SenderEmail property.
     * 
     * @param string SenderEmail
     * @return this instance
     */
    public function setSenderEmail($value) 
    {
        $this->_fields['SenderEmail']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the SenderEmail and returns this instance
     * 
     * @param string $value SenderEmail
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withSenderEmail($value)
    {
        $this->setSenderEmail($value);
        return $this;
    }


    /**
     * Checks if SenderEmail is set
     * 
     * @return bool true if SenderEmail  is set
     */
    public function isSetSenderEmail()
    {
        return !is_null($this->_fields['SenderEmail']['FieldValue']);
    }

    /**
     * Gets the value of the CallerName property.
     * 
     * @return string CallerName
     */
    public function getCallerName() 
    {
        return $this->_fields['CallerName']['FieldValue'];
    }

    /**
     * Sets the value of the CallerName property.
     * 
     * @param string CallerName
     * @return this instance
     */
    public function setCallerName($value) 
    {
        $this->_fields['CallerName']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the CallerName and returns this instance
     * 
     * @param string $value CallerName
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withCallerName($value)
    {
        $this->setCallerName($value);
        return $this;
    }


    /**
     * Checks if CallerName is set
     * 
     * @return bool true if CallerName  is set
     */
    public function isSetCallerName()
    {
        return !is_null($this->_fields['CallerName']['FieldValue']);
    }

    /**
     * Gets the value of the RecipientName property.
     * 
     * @return string RecipientName
     */
    public function getRecipientName() 
    {
        return $this->_fields['RecipientName']['FieldValue'];
    }

    /**
     * Sets the value of the RecipientName property.
     * 
     * @param string RecipientName
     * @return this instance
     */
    public function setRecipientName($value) 
    {
        $this->_fields['RecipientName']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the RecipientName and returns this instance
     * 
     * @param string $value RecipientName
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withRecipientName($value)
    {
        $this->setRecipientName($value);
        return $this;
    }


    /**
     * Checks if RecipientName is set
     * 
     * @return bool true if RecipientName  is set
     */
    public function isSetRecipientName()
    {
        return !is_null($this->_fields['RecipientName']['FieldValue']);
    }

    /**
     * Gets the value of the RecipientEmail property.
     * 
     * @return string RecipientEmail
     */
    public function getRecipientEmail() 
    {
        return $this->_fields['RecipientEmail']['FieldValue'];
    }

    /**
     * Sets the value of the RecipientEmail property.
     * 
     * @param string RecipientEmail
     * @return this instance
     */
    public function setRecipientEmail($value) 
    {
        $this->_fields['RecipientEmail']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the RecipientEmail and returns this instance
     * 
     * @param string $value RecipientEmail
     * @return Amazon_FPS_Model_TransactionDetail instance
     */
    public function withRecipientEmail($value)
    {
        $this->setRecipientEmail($value);
        return $this;
    }


    /**
     * Checks if RecipientEmail is set
     * 
     * @return bool true if RecipientEmail  is set
     */
    public function isSetRecipientEmail()
    {
        return !is_null($this->_fields['RecipientEmail']['FieldValue']);
    }

    /**
     * Gets the value of the RelatedTransaction.
     * 
     * @return array of RelatedTransaction RelatedTransaction
     */
    public function getRelatedTransaction() 
    {
        return $this->_fields['RelatedTransaction']['FieldValue'];
    }

    /**
     * Sets the value of the RelatedTransaction.
     * 
     * @param mixed RelatedTransaction or an array of RelatedTransaction RelatedTransaction
     * @return this instance
     */
    public function setRelatedTransaction($relatedTransaction) 
    {
        if (!$this->_isNumericArray($relatedTransaction)) {
            $relatedTransaction =  array ($relatedTransaction);    
        }
        $this->_fields['RelatedTransaction']['FieldValue'] = $relatedTransaction;
        return $this;
    }


    /**
     * Sets single or multiple values of RelatedTransaction list via variable number of arguments. 
     * For example, to set the list with two elements, simply pass two values as arguments to this function
     * <code>withRelatedTransaction($relatedTransaction1, $relatedTransaction2)</code>
     * 
     * @param RelatedTransaction  $relatedTransactionArgs one or more RelatedTransaction
     * @return Amazon_FPS_Model_TransactionDetail  instance
     */
    public function withRelatedTransaction($relatedTransactionArgs)
    {
        foreach (func_get_args() as $relatedTransaction) {
            $this->_fields['RelatedTransaction']['FieldValue'][] = $relatedTransaction;
        }
        return $this;
    }   



    /**
     * Checks if RelatedTransaction list is non-empty
     * 
     * @return bool true if RelatedTransaction list is non-empty
     */
    public function isSetRelatedTransaction()
    {
        return count ($this->_fields['RelatedTransaction']['FieldValue']) > 0;
    }

    /**
     * Gets the value of the StatusHistory.
     * 
     * @return array of StatusHistory StatusHistory
     */
    public function getStatusHistory() 
    {
        return $this->_fields['StatusHistory']['FieldValue'];
    }

    /**
     * Sets the value of the StatusHistory.
     * 
     * @param mixed StatusHistory or an array of StatusHistory StatusHistory
     * @return this instance
     */
    public function setStatusHistory($statusHistory) 
    {
        if (!$this->_isNumericArray($statusHistory)) {
            $statusHistory =  array ($statusHistory);    
        }
        $this->_fields['StatusHistory']['FieldValue'] = $statusHistory;
        return $this;
    }


    /**
     * Sets single or multiple values of StatusHistory list via variable number of arguments. 
     * For example, to set the list with two elements, simply pass two values as arguments to this function
     * <code>withStatusHistory($statusHistory1, $statusHistory2)</code>
     * 
     * @param StatusHistory  $statusHistoryArgs one or more StatusHistory
     * @return Amazon_FPS_Model_TransactionDetail  instance
     */
    public function withStatusHistory($statusHistoryArgs)
    {
        foreach (func_get_args() as $statusHistory) {
            $this->_fields['StatusHistory']['FieldValue'][] = $statusHistory;
        }
        return $this;
    }   



    /**
     * Checks if StatusHistory list is non-empty
     * 
     * @return bool true if StatusHistory list is non-empty
     */
    public function isSetStatusHistory()
    {
        return count ($this->_fields['StatusHistory']['FieldValue']) > 0;
    }




}