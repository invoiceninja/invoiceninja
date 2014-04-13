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
 * Amazon_FPS_Model_OutstandingPrepaidLiability
 * 
 * Properties:
 * <ul>
 * 
 * <li>OutstandingBalance: Amazon_FPS_Model_Amount</li>
 * <li>PendingInBalance: Amazon_FPS_Model_Amount</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_OutstandingPrepaidLiability extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_OutstandingPrepaidLiability
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>OutstandingBalance: Amazon_FPS_Model_Amount</li>
     * <li>PendingInBalance: Amazon_FPS_Model_Amount</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'OutstandingBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'PendingInBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the OutstandingBalance.
     * 
     * @return Amount OutstandingBalance
     */
    public function getOutstandingBalance() 
    {
        return $this->_fields['OutstandingBalance']['FieldValue'];
    }

    /**
     * Sets the value of the OutstandingBalance.
     * 
     * @param Amount OutstandingBalance
     * @return void
     */
    public function setOutstandingBalance($value) 
    {
        $this->_fields['OutstandingBalance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the OutstandingBalance  and returns this instance
     * 
     * @param Amount $value OutstandingBalance
     * @return Amazon_FPS_Model_OutstandingPrepaidLiability instance
     */
    public function withOutstandingBalance($value)
    {
        $this->setOutstandingBalance($value);
        return $this;
    }


    /**
     * Checks if OutstandingBalance  is set
     * 
     * @return bool true if OutstandingBalance property is set
     */
    public function isSetOutstandingBalance()
    {
        return !is_null($this->_fields['OutstandingBalance']['FieldValue']);

    }

    /**
     * Gets the value of the PendingInBalance.
     * 
     * @return Amount PendingInBalance
     */
    public function getPendingInBalance() 
    {
        return $this->_fields['PendingInBalance']['FieldValue'];
    }

    /**
     * Sets the value of the PendingInBalance.
     * 
     * @param Amount PendingInBalance
     * @return void
     */
    public function setPendingInBalance($value) 
    {
        $this->_fields['PendingInBalance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the PendingInBalance  and returns this instance
     * 
     * @param Amount $value PendingInBalance
     * @return Amazon_FPS_Model_OutstandingPrepaidLiability instance
     */
    public function withPendingInBalance($value)
    {
        $this->setPendingInBalance($value);
        return $this;
    }


    /**
     * Checks if PendingInBalance  is set
     * 
     * @return bool true if PendingInBalance property is set
     */
    public function isSetPendingInBalance()
    {
        return !is_null($this->_fields['PendingInBalance']['FieldValue']);

    }




}