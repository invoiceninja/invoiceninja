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
 * Amazon_FPS_Model_TokenUsageLimit
 * 
 * Properties:
 * <ul>
 * 
 * <li>Count: int</li>
 * <li>Amount: Amazon_FPS_Model_Amount</li>
 * <li>LastResetCount: int</li>
 * <li>LastResetAmount: Amazon_FPS_Model_Amount</li>
 * <li>LastResetTimestamp: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_TokenUsageLimit extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_TokenUsageLimit
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>Count: int</li>
     * <li>Amount: Amazon_FPS_Model_Amount</li>
     * <li>LastResetCount: int</li>
     * <li>LastResetAmount: Amazon_FPS_Model_Amount</li>
     * <li>LastResetTimestamp: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'Count' => array('FieldValue' => null, 'FieldType' => 'int'),
        'Amount' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'LastResetCount' => array('FieldValue' => null, 'FieldType' => 'int'),
        'LastResetAmount' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'LastResetTimestamp' => array('FieldValue' => null, 'FieldType' => 'string'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the Count property.
     * 
     * @return int Count
     */
    public function getCount() 
    {
        return $this->_fields['Count']['FieldValue'];
    }

    /**
     * Sets the value of the Count property.
     * 
     * @param int Count
     * @return this instance
     */
    public function setCount($value) 
    {
        $this->_fields['Count']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the Count and returns this instance
     * 
     * @param int $value Count
     * @return Amazon_FPS_Model_TokenUsageLimit instance
     */
    public function withCount($value)
    {
        $this->setCount($value);
        return $this;
    }


    /**
     * Checks if Count is set
     * 
     * @return bool true if Count  is set
     */
    public function isSetCount()
    {
        return !is_null($this->_fields['Count']['FieldValue']);
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
     * @return Amazon_FPS_Model_TokenUsageLimit instance
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

    /**
     * Gets the value of the LastResetCount property.
     * 
     * @return int LastResetCount
     */
    public function getLastResetCount() 
    {
        return $this->_fields['LastResetCount']['FieldValue'];
    }

    /**
     * Sets the value of the LastResetCount property.
     * 
     * @param int LastResetCount
     * @return this instance
     */
    public function setLastResetCount($value) 
    {
        $this->_fields['LastResetCount']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the LastResetCount and returns this instance
     * 
     * @param int $value LastResetCount
     * @return Amazon_FPS_Model_TokenUsageLimit instance
     */
    public function withLastResetCount($value)
    {
        $this->setLastResetCount($value);
        return $this;
    }


    /**
     * Checks if LastResetCount is set
     * 
     * @return bool true if LastResetCount  is set
     */
    public function isSetLastResetCount()
    {
        return !is_null($this->_fields['LastResetCount']['FieldValue']);
    }

    /**
     * Gets the value of the LastResetAmount.
     * 
     * @return Amount LastResetAmount
     */
    public function getLastResetAmount() 
    {
        return $this->_fields['LastResetAmount']['FieldValue'];
    }

    /**
     * Sets the value of the LastResetAmount.
     * 
     * @param Amount LastResetAmount
     * @return void
     */
    public function setLastResetAmount($value) 
    {
        $this->_fields['LastResetAmount']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the LastResetAmount  and returns this instance
     * 
     * @param Amount $value LastResetAmount
     * @return Amazon_FPS_Model_TokenUsageLimit instance
     */
    public function withLastResetAmount($value)
    {
        $this->setLastResetAmount($value);
        return $this;
    }


    /**
     * Checks if LastResetAmount  is set
     * 
     * @return bool true if LastResetAmount property is set
     */
    public function isSetLastResetAmount()
    {
        return !is_null($this->_fields['LastResetAmount']['FieldValue']);

    }

    /**
     * Gets the value of the LastResetTimestamp property.
     * 
     * @return string LastResetTimestamp
     */
    public function getLastResetTimestamp() 
    {
        return $this->_fields['LastResetTimestamp']['FieldValue'];
    }

    /**
     * Sets the value of the LastResetTimestamp property.
     * 
     * @param string LastResetTimestamp
     * @return this instance
     */
    public function setLastResetTimestamp($value) 
    {
        $this->_fields['LastResetTimestamp']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the LastResetTimestamp and returns this instance
     * 
     * @param string $value LastResetTimestamp
     * @return Amazon_FPS_Model_TokenUsageLimit instance
     */
    public function withLastResetTimestamp($value)
    {
        $this->setLastResetTimestamp($value);
        return $this;
    }


    /**
     * Checks if LastResetTimestamp is set
     * 
     * @return bool true if LastResetTimestamp  is set
     */
    public function isSetLastResetTimestamp()
    {
        return !is_null($this->_fields['LastResetTimestamp']['FieldValue']);
    }




}