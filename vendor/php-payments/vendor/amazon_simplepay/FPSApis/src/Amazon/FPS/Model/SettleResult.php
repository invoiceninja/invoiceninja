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
 * Amazon_FPS_Model_SettleResult
 * 
 * Properties:
 * <ul>
 * 
 * <li>TransactionId: string</li>
 * <li>TransactionStatus: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_SettleResult extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_SettleResult
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TransactionId: string</li>
     * <li>TransactionStatus: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionStatus' => array('FieldValue' => null, 'FieldType' => 'string'),
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
     * @return Amazon_FPS_Model_SettleResult instance
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
     * @return Amazon_FPS_Model_SettleResult instance
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




}