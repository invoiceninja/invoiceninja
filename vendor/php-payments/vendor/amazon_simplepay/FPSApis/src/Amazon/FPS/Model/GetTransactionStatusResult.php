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
 * Amazon_FPS_Model_GetTransactionStatusResult
 * 
 * Properties:
 * <ul>
 * 
 * <li>TransactionId: string</li>
 * <li>TransactionStatus: string</li>
 * <li>CallerReference: string</li>
 * <li>StatusCode: string</li>
 * <li>StatusMessage: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_GetTransactionStatusResult extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_GetTransactionStatusResult
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TransactionId: string</li>
     * <li>TransactionStatus: string</li>
     * <li>CallerReference: string</li>
     * <li>StatusCode: string</li>
     * <li>StatusMessage: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionStatus' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerReference' => array('FieldValue' => null, 'FieldType' => 'string'),
        'StatusCode' => array('FieldValue' => null, 'FieldType' => 'string'),
        'StatusMessage' => array('FieldValue' => null, 'FieldType' => 'string'),
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
     * @return Amazon_FPS_Model_GetTransactionStatusResult instance
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
     * @return Amazon_FPS_Model_GetTransactionStatusResult instance
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
     * @return Amazon_FPS_Model_GetTransactionStatusResult instance
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
     * @return Amazon_FPS_Model_GetTransactionStatusResult instance
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
     * @return Amazon_FPS_Model_GetTransactionStatusResult instance
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




}