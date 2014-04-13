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
 * Amazon_FPS_Model_PrepaidBalance
 * 
 * Properties:
 * <ul>
 * 
 * <li>AvailableBalance: Amazon_FPS_Model_Amount</li>
 * <li>PendingInBalance: Amazon_FPS_Model_Amount</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_PrepaidBalance extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_PrepaidBalance
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>AvailableBalance: Amazon_FPS_Model_Amount</li>
     * <li>PendingInBalance: Amazon_FPS_Model_Amount</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'AvailableBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'PendingInBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the AvailableBalance.
     * 
     * @return Amount AvailableBalance
     */
    public function getAvailableBalance() 
    {
        return $this->_fields['AvailableBalance']['FieldValue'];
    }

    /**
     * Sets the value of the AvailableBalance.
     * 
     * @param Amount AvailableBalance
     * @return void
     */
    public function setAvailableBalance($value) 
    {
        $this->_fields['AvailableBalance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the AvailableBalance  and returns this instance
     * 
     * @param Amount $value AvailableBalance
     * @return Amazon_FPS_Model_PrepaidBalance instance
     */
    public function withAvailableBalance($value)
    {
        $this->setAvailableBalance($value);
        return $this;
    }


    /**
     * Checks if AvailableBalance  is set
     * 
     * @return bool true if AvailableBalance property is set
     */
    public function isSetAvailableBalance()
    {
        return !is_null($this->_fields['AvailableBalance']['FieldValue']);

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
     * @return Amazon_FPS_Model_PrepaidBalance instance
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