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
 * Amazon_FPS_Model_RefundRequest
 * 
 * Properties:
 * <ul>
 * 
 * <li>TransactionId: string</li>
 * <li>RefundAmount: Amazon_FPS_Model_Amount</li>
 * <li>CallerReference: string</li>
 * <li>CallerDescription: string</li>
 * <li>MarketplaceRefundPolicy: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_RefundRequest extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_RefundRequest
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TransactionId: string</li>
     * <li>RefundAmount: Amazon_FPS_Model_Amount</li>
     * <li>CallerReference: string</li>
     * <li>CallerDescription: string</li>
     * <li>MarketplaceRefundPolicy: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RefundAmount' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        'CallerReference' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerDescription' => array('FieldValue' => null, 'FieldType' => 'string'),
        'MarketplaceRefundPolicy' => array('FieldValue' => null, 'FieldType' => 'string'),
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
     * @return Amazon_FPS_Model_RefundRequest instance
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
     * Gets the value of the RefundAmount.
     * 
     * @return Amount RefundAmount
     */
    public function getRefundAmount() 
    {
        return $this->_fields['RefundAmount']['FieldValue'];
    }

    /**
     * Sets the value of the RefundAmount.
     * 
     * @param Amount RefundAmount
     * @return void
     */
    public function setRefundAmount($value) 
    {
        $this->_fields['RefundAmount']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the RefundAmount  and returns this instance
     * 
     * @param Amount $value RefundAmount
     * @return Amazon_FPS_Model_RefundRequest instance
     */
    public function withRefundAmount($value)
    {
        $this->setRefundAmount($value);
        return $this;
    }


    /**
     * Checks if RefundAmount  is set
     * 
     * @return bool true if RefundAmount property is set
     */
    public function isSetRefundAmount()
    {
        return !is_null($this->_fields['RefundAmount']['FieldValue']);

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
     * @return Amazon_FPS_Model_RefundRequest instance
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
     * Gets the value of the CallerDescription property.
     * 
     * @return string CallerDescription
     */
    public function getCallerDescription() 
    {
        return $this->_fields['CallerDescription']['FieldValue'];
    }

    /**
     * Sets the value of the CallerDescription property.
     * 
     * @param string CallerDescription
     * @return this instance
     */
    public function setCallerDescription($value) 
    {
        $this->_fields['CallerDescription']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the CallerDescription and returns this instance
     * 
     * @param string $value CallerDescription
     * @return Amazon_FPS_Model_RefundRequest instance
     */
    public function withCallerDescription($value)
    {
        $this->setCallerDescription($value);
        return $this;
    }


    /**
     * Checks if CallerDescription is set
     * 
     * @return bool true if CallerDescription  is set
     */
    public function isSetCallerDescription()
    {
        return !is_null($this->_fields['CallerDescription']['FieldValue']);
    }

    /**
     * Gets the value of the MarketplaceRefundPolicy property.
     * 
     * @return string MarketplaceRefundPolicy
     */
    public function getMarketplaceRefundPolicy() 
    {
        return $this->_fields['MarketplaceRefundPolicy']['FieldValue'];
    }

    /**
     * Sets the value of the MarketplaceRefundPolicy property.
     * 
     * @param string MarketplaceRefundPolicy
     * @return this instance
     */
    public function setMarketplaceRefundPolicy($value) 
    {
        $this->_fields['MarketplaceRefundPolicy']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the MarketplaceRefundPolicy and returns this instance
     * 
     * @param string $value MarketplaceRefundPolicy
     * @return Amazon_FPS_Model_RefundRequest instance
     */
    public function withMarketplaceRefundPolicy($value)
    {
        $this->setMarketplaceRefundPolicy($value);
        return $this;
    }


    /**
     * Checks if MarketplaceRefundPolicy is set
     * 
     * @return bool true if MarketplaceRefundPolicy  is set
     */
    public function isSetMarketplaceRefundPolicy()
    {
        return !is_null($this->_fields['MarketplaceRefundPolicy']['FieldValue']);
    }




}