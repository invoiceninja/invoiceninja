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
 * Amazon_FPS_Model_SettleRequest
 * 
 * Properties:
 * <ul>
 * 
 * <li>ReserveTransactionId: string</li>
 * <li>TransactionAmount: Amazon_FPS_Model_Amount</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_SettleRequest extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_SettleRequest
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>ReserveTransactionId: string</li>
     * <li>TransactionAmount: Amazon_FPS_Model_Amount</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'ReserveTransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TransactionAmount' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the ReserveTransactionId property.
     * 
     * @return string ReserveTransactionId
     */
    public function getReserveTransactionId() 
    {
        return $this->_fields['ReserveTransactionId']['FieldValue'];
    }

    /**
     * Sets the value of the ReserveTransactionId property.
     * 
     * @param string ReserveTransactionId
     * @return this instance
     */
    public function setReserveTransactionId($value) 
    {
        $this->_fields['ReserveTransactionId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the ReserveTransactionId and returns this instance
     * 
     * @param string $value ReserveTransactionId
     * @return Amazon_FPS_Model_SettleRequest instance
     */
    public function withReserveTransactionId($value)
    {
        $this->setReserveTransactionId($value);
        return $this;
    }


    /**
     * Checks if ReserveTransactionId is set
     * 
     * @return bool true if ReserveTransactionId  is set
     */
    public function isSetReserveTransactionId()
    {
        return !is_null($this->_fields['ReserveTransactionId']['FieldValue']);
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
     * @return Amazon_FPS_Model_SettleRequest instance
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




}