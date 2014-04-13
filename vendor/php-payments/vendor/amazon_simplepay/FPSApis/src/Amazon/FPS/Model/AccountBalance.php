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
 * Amazon_FPS_Model_AccountBalance
 * 
 * Properties:
 * <ul>
 * 
 * <li>TotalBalance: Amazon_FPS_Model_Amount</li>
 * <li>PendingInBalance: Amazon_FPS_Model_Amount</li>
 * <li>PendingOutBalance: Amazon_FPS_Model_Amount</li>
 * <li>AvailableBalances: Amazon_FPS_Model_AvailableBalances</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_AccountBalance extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_AccountBalance
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TotalBalance: Amazon_FPS_Model_Amount</li>
     * <li>PendingInBalance: Amazon_FPS_Model_Amount</li>
     * <li>PendingOutBalance: Amazon_FPS_Model_Amount</li>
     * <li>AvailableBalances: Amazon_FPS_Model_AvailableBalances</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TotalBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'PendingInBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'PendingOutBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'AvailableBalances' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_AvailableBalances'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the TotalBalance.
     * 
     * @return Amount TotalBalance
     */
    public function getTotalBalance() 
    {
        return $this->_fields['TotalBalance']['FieldValue'];
    }

    /**
     * Sets the value of the TotalBalance.
     * 
     * @param Amount TotalBalance
     * @return void
     */
    public function setTotalBalance($value) 
    {
        $this->_fields['TotalBalance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the TotalBalance  and returns this instance
     * 
     * @param Amount $value TotalBalance
     * @return Amazon_FPS_Model_AccountBalance instance
     */
    public function withTotalBalance($value)
    {
        $this->setTotalBalance($value);
        return $this;
    }


    /**
     * Checks if TotalBalance  is set
     * 
     * @return bool true if TotalBalance property is set
     */
    public function isSetTotalBalance()
    {
        return !is_null($this->_fields['TotalBalance']['FieldValue']);

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
     * @return Amazon_FPS_Model_AccountBalance instance
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

    /**
     * Gets the value of the PendingOutBalance.
     * 
     * @return Amount PendingOutBalance
     */
    public function getPendingOutBalance() 
    {
        return $this->_fields['PendingOutBalance']['FieldValue'];
    }

    /**
     * Sets the value of the PendingOutBalance.
     * 
     * @param Amount PendingOutBalance
     * @return void
     */
    public function setPendingOutBalance($value) 
    {
        $this->_fields['PendingOutBalance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the PendingOutBalance  and returns this instance
     * 
     * @param Amount $value PendingOutBalance
     * @return Amazon_FPS_Model_AccountBalance instance
     */
    public function withPendingOutBalance($value)
    {
        $this->setPendingOutBalance($value);
        return $this;
    }


    /**
     * Checks if PendingOutBalance  is set
     * 
     * @return bool true if PendingOutBalance property is set
     */
    public function isSetPendingOutBalance()
    {
        return !is_null($this->_fields['PendingOutBalance']['FieldValue']);

    }

    /**
     * Gets the value of the AvailableBalances.
     * 
     * @return AvailableBalances AvailableBalances
     */
    public function getAvailableBalances() 
    {
        return $this->_fields['AvailableBalances']['FieldValue'];
    }

    /**
     * Sets the value of the AvailableBalances.
     * 
     * @param AvailableBalances AvailableBalances
     * @return void
     */
    public function setAvailableBalances($value) 
    {
        $this->_fields['AvailableBalances']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the AvailableBalances  and returns this instance
     * 
     * @param AvailableBalances $value AvailableBalances
     * @return Amazon_FPS_Model_AccountBalance instance
     */
    public function withAvailableBalances($value)
    {
        $this->setAvailableBalances($value);
        return $this;
    }


    /**
     * Checks if AvailableBalances  is set
     * 
     * @return bool true if AvailableBalances property is set
     */
    public function isSetAvailableBalances()
    {
        return !is_null($this->_fields['AvailableBalances']['FieldValue']);

    }




}