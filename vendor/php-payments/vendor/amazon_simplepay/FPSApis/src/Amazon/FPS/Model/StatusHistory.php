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
 * Amazon_FPS_Model_StatusHistory
 * 
 * Properties:
 * <ul>
 * 
 * <li>Date: string</li>
 * <li>TransactionStatus: string</li>
 * <li>StatusCode: string</li>
 * <li>Amount: Amazon_FPS_Model_Amount</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_StatusHistory extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_StatusHistory
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>Date: string</li>
     * <li>TransactionStatus: string</li>
     * <li>StatusCode: string</li>
     * <li>Amount: Amazon_FPS_Model_Amount</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'Date' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionStatus' => array('FieldValue' => null, 'FieldType' => 'string'),
        'StatusCode' => array('FieldValue' => null, 'FieldType' => 'string'),
        'Amount' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the Date property.
     * 
     * @return string Date
     */
    public function getDate() 
    {
        return $this->_fields['Date']['FieldValue'];
    }

    /**
     * Sets the value of the Date property.
     * 
     * @param string Date
     * @return this instance
     */
    public function setDate($value) 
    {
        $this->_fields['Date']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the Date and returns this instance
     * 
     * @param string $value Date
     * @return Amazon_FPS_Model_StatusHistory instance
     */
    public function withDate($value)
    {
        $this->setDate($value);
        return $this;
    }


    /**
     * Checks if Date is set
     * 
     * @return bool true if Date  is set
     */
    public function isSetDate()
    {
        return !is_null($this->_fields['Date']['FieldValue']);
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
     * @return Amazon_FPS_Model_StatusHistory instance
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
     * @return Amazon_FPS_Model_StatusHistory instance
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
     * Gets the value of the Amount.
     * 
     * @return Amount Amount
     */
    public function getAmount() 
    {
        return $this->_fields['Amount']['FieldValue'];
    }

    /**
     * Sets the value of the Amount.
     * 
     * @param Amount Amount
     * @return void
     */
    public function setAmount($value) 
    {
        $this->_fields['Amount']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the Amount  and returns this instance
     * 
     * @param Amount $value Amount
     * @return Amazon_FPS_Model_StatusHistory instance
     */
    public function withAmount($value)
    {
        $this->setAmount($value);
        return $this;
    }


    /**
     * Checks if Amount  is set
     * 
     * @return bool true if Amount property is set
     */
    public function isSetAmount()
    {
        return !is_null($this->_fields['Amount']['FieldValue']);

    }




}