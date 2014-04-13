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
 * Amazon_FPS_Model_RelatedTransaction
 * 
 * Properties:
 * <ul>
 * 
 * <li>TransactionId: string</li>
 * <li>RelationType: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_RelatedTransaction extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_RelatedTransaction
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TransactionId: string</li>
     * <li>RelationType: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TransactionId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'RelationType' => array('FieldValue' => null, 'FieldType' => 'string'),
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
     * @return Amazon_FPS_Model_RelatedTransaction instance
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
     * Gets the value of the RelationType property.
     * 
     * @return string RelationType
     */
    public function getRelationType() 
    {
        return $this->_fields['RelationType']['FieldValue'];
    }

    /**
     * Sets the value of the RelationType property.
     * 
     * @param string RelationType
     * @return this instance
     */
    public function setRelationType($value) 
    {
        $this->_fields['RelationType']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the RelationType and returns this instance
     * 
     * @param string $value RelationType
     * @return Amazon_FPS_Model_RelatedTransaction instance
     */
    public function withRelationType($value)
    {
        $this->setRelationType($value);
        return $this;
    }


    /**
     * Checks if RelationType is set
     * 
     * @return bool true if RelationType  is set
     */
    public function isSetRelationType()
    {
        return !is_null($this->_fields['RelationType']['FieldValue']);
    }




}