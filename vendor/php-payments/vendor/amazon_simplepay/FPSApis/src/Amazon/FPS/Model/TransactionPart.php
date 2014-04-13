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
 * Amazon_FPS_Model_TransactionPart
 * 
 * Properties:
 * <ul>
 * 
 * <li>InstrumentId: string</li>
 * <li>Role: string</li>
 * <li>Name: string</li>
 * <li>Reference: string</li>
 * <li>Description: string</li>
 * <li>FeesPaid: Amazon_FPS_Model_Amount</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_TransactionPart extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_TransactionPart
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>InstrumentId: string</li>
     * <li>Role: string</li>
     * <li>Name: string</li>
     * <li>Reference: string</li>
     * <li>Description: string</li>
     * <li>FeesPaid: Amazon_FPS_Model_Amount</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'InstrumentId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'Role' => array('FieldValue' => null, 'FieldType' => 'string'),
        'Name' => array('FieldValue' => null, 'FieldType' => 'string'),
        'Reference' => array('FieldValue' => null, 'FieldType' => 'string'),
        'Description' => array('FieldValue' => null, 'FieldType' => 'string'),
        'FeesPaid' => array('FieldValue' => null, 'FieldType' => 'Amazon_FPS_Model_Amount'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the InstrumentId property.
     * 
     * @return string InstrumentId
     */
    public function getInstrumentId() 
    {
        return $this->_fields['InstrumentId']['FieldValue'];
    }

    /**
     * Sets the value of the InstrumentId property.
     * 
     * @param string InstrumentId
     * @return this instance
     */
    public function setInstrumentId($value) 
    {
        $this->_fields['InstrumentId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the InstrumentId and returns this instance
     * 
     * @param string $value InstrumentId
     * @return Amazon_FPS_Model_TransactionPart instance
     */
    public function withInstrumentId($value)
    {
        $this->setInstrumentId($value);
        return $this;
    }


    /**
     * Checks if InstrumentId is set
     * 
     * @return bool true if InstrumentId  is set
     */
    public function isSetInstrumentId()
    {
        return !is_null($this->_fields['InstrumentId']['FieldValue']);
    }

    /**
     * Gets the value of the Role property.
     * 
     * @return string Role
     */
    public function getRole() 
    {
        return $this->_fields['Role']['FieldValue'];
    }

    /**
     * Sets the value of the Role property.
     * 
     * @param string Role
     * @return this instance
     */
    public function setRole($value) 
    {
        $this->_fields['Role']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the Role and returns this instance
     * 
     * @param string $value Role
     * @return Amazon_FPS_Model_TransactionPart instance
     */
    public function withRole($value)
    {
        $this->setRole($value);
        return $this;
    }


    /**
     * Checks if Role is set
     * 
     * @return bool true if Role  is set
     */
    public function isSetRole()
    {
        return !is_null($this->_fields['Role']['FieldValue']);
    }

    /**
     * Gets the value of the Name property.
     * 
     * @return string Name
     */
    public function getName() 
    {
        return $this->_fields['Name']['FieldValue'];
    }

    /**
     * Sets the value of the Name property.
     * 
     * @param string Name
     * @return this instance
     */
    public function setName($value) 
    {
        $this->_fields['Name']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the Name and returns this instance
     * 
     * @param string $value Name
     * @return Amazon_FPS_Model_TransactionPart instance
     */
    public function withName($value)
    {
        $this->setName($value);
        return $this;
    }


    /**
     * Checks if Name is set
     * 
     * @return bool true if Name  is set
     */
    public function isSetName()
    {
        return !is_null($this->_fields['Name']['FieldValue']);
    }

    /**
     * Gets the value of the Reference property.
     * 
     * @return string Reference
     */
    public function getReference() 
    {
        return $this->_fields['Reference']['FieldValue'];
    }

    /**
     * Sets the value of the Reference property.
     * 
     * @param string Reference
     * @return this instance
     */
    public function setReference($value) 
    {
        $this->_fields['Reference']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the Reference and returns this instance
     * 
     * @param string $value Reference
     * @return Amazon_FPS_Model_TransactionPart instance
     */
    public function withReference($value)
    {
        $this->setReference($value);
        return $this;
    }


    /**
     * Checks if Reference is set
     * 
     * @return bool true if Reference  is set
     */
    public function isSetReference()
    {
        return !is_null($this->_fields['Reference']['FieldValue']);
    }

    /**
     * Gets the value of the Description property.
     * 
     * @return string Description
     */
    public function getDescription() 
    {
        return $this->_fields['Description']['FieldValue'];
    }

    /**
     * Sets the value of the Description property.
     * 
     * @param string Description
     * @return this instance
     */
    public function setDescription($value) 
    {
        $this->_fields['Description']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the Description and returns this instance
     * 
     * @param string $value Description
     * @return Amazon_FPS_Model_TransactionPart instance
     */
    public function withDescription($value)
    {
        $this->setDescription($value);
        return $this;
    }


    /**
     * Checks if Description is set
     * 
     * @return bool true if Description  is set
     */
    public function isSetDescription()
    {
        return !is_null($this->_fields['Description']['FieldValue']);
    }

    /**
     * Gets the value of the FeesPaid.
     * 
     * @return Amount FeesPaid
     */
    public function getFeesPaid() 
    {
        return $this->_fields['FeesPaid']['FieldValue'];
    }

    /**
     * Sets the value of the FeesPaid.
     * 
     * @param Amount FeesPaid
     * @return void
     */
    public function setFeesPaid($value) 
    {
        $this->_fields['FeesPaid']['FieldValue'] = $value;
        return;
    }

    /**
     * Sets the value of the FeesPaid  and returns this instance
     * 
     * @param Amount $value FeesPaid
     * @return Amazon_FPS_Model_TransactionPart instance
     */
    public function withFeesPaid($value)
    {
        $this->setFeesPaid($value);
        return $this;
    }


    /**
     * Checks if FeesPaid  is set
     * 
     * @return bool true if FeesPaid property is set
     */
    public function isSetFeesPaid()
    {
        return !is_null($this->_fields['FeesPaid']['FieldValue']);

    }




}