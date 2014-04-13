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
 * Amazon_FPS_Model_AvailableBalances
 * 
 * Properties:
 * <ul>
 * 
 * <li>DisburseBalance: Amazon_FPS_Model_Amount</li>
 * <li>RefundBalance: Amazon_FPS_Model_Amount</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_AvailableBalances extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_AvailableBalances
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>DisburseBalance: Amazon_FPS_Model_Amount</li>
     * <li>RefundBalance: Amazon_FPS_Model_Amount</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'DisburseBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'RefundBalance' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the DisburseBalance.
     * 
     * @return Amount DisburseBalance
     */
    public function getDisburseBalance() 
    {
        return $this->_fields['DisburseBalance']['FieldValue'];
    }

    /**
     * Sets the value of the DisburseBalance.
     * 
     * @param Amount DisburseBalance
     * @return void
     */
    public function setDisburseBalance($value) 
    {
        $this->_fields['DisburseBalance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the DisburseBalance  and returns this instance
     * 
     * @param Amount $value DisburseBalance
     * @return Amazon_FPS_Model_AvailableBalances instance
     */
    public function withDisburseBalance($value)
    {
        $this->setDisburseBalance($value);
        return $this;
    }


    /**
     * Checks if DisburseBalance  is set
     * 
     * @return bool true if DisburseBalance property is set
     */
    public function isSetDisburseBalance()
    {
        return !is_null($this->_fields['DisburseBalance']['FieldValue']);

    }

    /**
     * Gets the value of the RefundBalance.
     * 
     * @return Amount RefundBalance
     */
    public function getRefundBalance() 
    {
        return $this->_fields['RefundBalance']['FieldValue'];
    }

    /**
     * Sets the value of the RefundBalance.
     * 
     * @param Amount RefundBalance
     * @return void
     */
    public function setRefundBalance($value) 
    {
        $this->_fields['RefundBalance']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the RefundBalance  and returns this instance
     * 
     * @param Amount $value RefundBalance
     * @return Amazon_FPS_Model_AvailableBalances instance
     */
    public function withRefundBalance($value)
    {
        $this->setRefundBalance($value);
        return $this;
    }


    /**
     * Checks if RefundBalance  is set
     * 
     * @return bool true if RefundBalance property is set
     */
    public function isSetRefundBalance()
    {
        return !is_null($this->_fields['RefundBalance']['FieldValue']);

    }




}