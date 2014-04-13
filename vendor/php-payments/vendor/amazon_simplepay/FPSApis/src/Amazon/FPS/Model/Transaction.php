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
 * Amazon_FPS_Model_Transaction
 * 
 * Properties:
 * <ul>
 * 
 * <li>TransactionId: string</li>
 * <li>CallerTransactionDate: string</li>
 * <li>DateReceived: string</li>
 * <li>DateCompleted: string</li>
 * <li>TransactionAmount: Amazon_FPS_Model_Amount</li>
 * <li>FPSOperation: string</li>
 * <li>TransactionStatus: string</li>
 * <li>StatusMessage: string</li>
 * <li>StatusCode: string</li>
 * <li>OriginalTransactionId: string</li>
 * <li>TransactionPart: Amazon_FPS_Model_TransactionPart</li>
 * <li>PaymentMethod: string</li>
 * <li>SenderName: string</li>
 * <li>CallerName: string</li>
 * <li>RecipientName: string</li>
 * <li>FPSFees: Amazon_FPS_Model_Amount</li>
 * <li>Balance: Amazon_FPS_Model_Amount</li>
 * <li>SenderTokenId: string</li>
 * <li>RecipientTokenId: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_Transaction extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_Transaction
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TransactionId: string</li>
     * <li>CallerTransactionDate: string</li>
     * <li>DateReceived: string</li>
     * <li>DateCompleted: string</li>
     * <li>TransactionAmount: Amazon_FPS_Model_Amount</li>
     * <li>FPSOperation: string</li>
     * <li>TransactionStatus: string</li>
     * <li>StatusMessage: string</li>
     * <li>StatusCode: string</li>
     * <li>OriginalTransactionId: string</li>
     * <li>TransactionPart: Amazon_FPS_Model_TransactionPart</li>
     * <li>PaymentMethod: string</li>
     * <li>SenderName: string</li>
     * <li>CallerName: string</li>
     * <li>RecipientName: string</li>
     * <li>FPSFees: Amazon_FPS_Model_Amount</li>
     * <li>Balance: Amazon_FPS_Model_Amount</li>
     * <li>SenderTokenId: string</li>
     * <li>RecipientTokenId: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerTransactionDate' => array('FieldValue' => null, 'FieldType' => 'string'),
        'DateReceived' => array('FieldValue' => null, 'FieldType' => 'string'),
        'DateCompleted' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionAmount' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'FPSOperation' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionStatus' => array('FieldValue' => null, 'FieldType' => 'string'),
        'StatusMessage' => array('FieldValue' => null, 'FieldType' => 'string'),
        'StatusCode' => array('FieldValue' => null, 'FieldType' => 'string'),
        'OriginalTransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionPart' => array('FieldValue' => array(), 'FieldType' => array('Amazon_FPS_Model_TransactionPart')),
        'PaymentMethod' => array('FieldValue' => null, 'FieldType' => 'string'),
        'SenderName' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerName' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RecipientName' => array('FieldValue' => null, 'FieldType' => 'string'),
        'FPSFees' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'Balance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'SenderTokenId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RecipientTokenId' => array('FieldValue' => null, 'FieldType' => 'string'),
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * Gets the value of the CallerTransactionDate property.
     * 
     * @return string CallerTransactionDate
     */
    public function getCallerTransactionDate() 
    {
        return $this->_fields['CallerTransactionDate']['FieldValue'];
    }

    /**
     * Sets the value of the CallerTransactionDate property.
     * 
     * @param string CallerTransactionDate
     * @return this instance
     */
    public function setCallerTransactionDate($value) 
    {
        $this->_fields['CallerTransactionDate']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the CallerTransactionDate and returns this instance
     * 
     * @param string $value CallerTransactionDate
     * @return Amazon_FPS_Model_Transaction instance
     */
    public function withCallerTransactionDate($value)
    {
        $this->setCallerTransactionDate($value);
        return $this;
    }


    /**
     * Checks if CallerTransactionDate is set
     * 
     * @return bool true if CallerTransactionDate  is set
     */
    public function isSetCallerTransactionDate()
    {
        return !is_null($this->_fields['CallerTransactionDate']['FieldValue']);
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * Gets the value of the OriginalTransactionId property.
     * 
     * @return string OriginalTransactionId
     */
    public function getOriginalTransactionId() 
    {
        return $this->_fields['OriginalTransactionId']['FieldValue'];
    }

    /**
     * Sets the value of the OriginalTransactionId property.
     * 
     * @param string OriginalTransactionId
     * @return this instance
     */
    public function setOriginalTransactionId($value) 
    {
        $this->_fields['OriginalTransactionId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the OriginalTransactionId and returns this instance
     * 
     * @param string $value OriginalTransactionId
     * @return Amazon_FPS_Model_Transaction instance
     */
    public function withOriginalTransactionId($value)
    {
        $this->setOriginalTransactionId($value);
        return $this;
    }


    /**
     * Checks if OriginalTransactionId is set
     * 
     * @return bool true if OriginalTransactionId  is set
     */
    public function isSetOriginalTransactionId()
    {
        return !is_null($this->_fields['OriginalTransactionId']['FieldValue']);
    }

    /**
     * Gets the value of the TransactionPart.
     * 
     * @return array of TransactionPart TransactionPart
     */
    public function getTransactionPart() 
    {
        return $this->_fields['TransactionPart']['FieldValue'];
    }

    /**
     * Sets the value of the TransactionPart.
     * 
     * @param mixed TransactionPart or an array of TransactionPart TransactionPart
     * @return this instance
     */
    public function setTransactionPart($transactionPart) 
    {
        if (!$this->_isNumericArray($transactionPart)) {
            $transactionPart =  array ($transactionPart);    
        }
        $this->_fields['TransactionPart']['FieldValue'] = $transactionPart;
        return $this;
    }


    /**
     * Sets single or multiple values of TransactionPart list via variable number of arguments. 
     * For example, to set the list with two elements, simply pass two values as arguments to this function
     * <code>withTransactionPart($transactionPart1, $transactionPart2)</code>
     * 
     * @param TransactionPart  $transactionPartArgs one or more TransactionPart
     * @return Amazon_FPS_Model_Transaction  instance
     */
    public function withTransactionPart($transactionPartArgs)
    {
        foreach (func_get_args() as $transactionPart) {
            $this->_fields['TransactionPart']['FieldValue'][] = $transactionPart;
        }
        return $this;
    }   



    /**
     * Checks if TransactionPart list is non-empty
     * 
     * @return bool true if TransactionPart list is non-empty
     */
    public function isSetTransactionPart()
    {
        return count ($this->_fields['TransactionPart']['FieldValue']) > 0;
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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
     * Gets the value of the Balance.
     * 
     * @return Amount Balance
     */
    public function getBalance() 
    {
        return $this->_fields['Balance']['FieldValue'];
    }

    /**
     * Sets the value of the Balance.
     * 
     * @param Amount Balance
     * @return void
     */
    public function setBalance($value) 
    {
        $this->_fields['Balance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the Balance  and returns this instance
     * 
     * @param Amount $value Balance
     * @return Amazon_FPS_Model_Transaction instance
     */
    public function withBalance($value)
    {
        $this->setBalance($value);
        return $this;
    }


    /**
     * Checks if Balance  is set
     * 
     * @return bool true if Balance property is set
     */
    public function isSetBalance()
    {
        return !is_null($this->_fields['Balance']['FieldValue']);

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
     * @return Amazon_FPS_Model_Transaction instance
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
     * @return Amazon_FPS_Model_Transaction instance
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




}