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
 * Amazon_FPS_Model_Token
 * 
 * Properties:
 * <ul>
 * 
 * <li>TokenId: string</li>
 * <li>FriendlyName: string</li>
 * <li>TokenStatus: string</li>
 * <li>DateInstalled: string</li>
 * <li>CallerReference: string</li>
 * <li>TokenType: string</li>
 * <li>OldTokenId: string</li>
 * <li>PaymentReason: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_Token extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_Token
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>TokenId: string</li>
     * <li>FriendlyName: string</li>
     * <li>TokenStatus: string</li>
     * <li>DateInstalled: string</li>
     * <li>CallerReference: string</li>
     * <li>TokenType: string</li>
     * <li>OldTokenId: string</li>
     * <li>PaymentReason: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'TokenId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'FriendlyName' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TokenStatus' => array('FieldValue' => null, 'FieldType' => 'string'),
        'DateInstalled' => array('FieldValue' => null, 'FieldType' => 'string'),
        'CallerReference' => array('FieldValue' => null, 'FieldType' => 'string'),
        'TokenType' => array('FieldValue' => null, 'FieldType' => 'string'),
        'OldTokenId' => array('FieldValue' => null, 'FieldType' => 'string'),
        'PaymentReason' => array('FieldValue' => null, 'FieldType' => 'string'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the TokenId property.
     * 
     * @return string TokenId
     */
    public function getTokenId() 
    {
        return $this->_fields['TokenId']['FieldValue'];
    }

    /**
     * Sets the value of the TokenId property.
     * 
     * @param string TokenId
     * @return this instance
     */
    public function setTokenId($value) 
    {
        $this->_fields['TokenId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the TokenId and returns this instance
     * 
     * @param string $value TokenId
     * @return Amazon_FPS_Model_Token instance
     */
    public function withTokenId($value)
    {
        $this->setTokenId($value);
        return $this;
    }


    /**
     * Checks if TokenId is set
     * 
     * @return bool true if TokenId  is set
     */
    public function isSetTokenId()
    {
        return !is_null($this->_fields['TokenId']['FieldValue']);
    }

    /**
     * Gets the value of the FriendlyName property.
     * 
     * @return string FriendlyName
     */
    public function getFriendlyName() 
    {
        return $this->_fields['FriendlyName']['FieldValue'];
    }

    /**
     * Sets the value of the FriendlyName property.
     * 
     * @param string FriendlyName
     * @return this instance
     */
    public function setFriendlyName($value) 
    {
        $this->_fields['FriendlyName']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the FriendlyName and returns this instance
     * 
     * @param string $value FriendlyName
     * @return Amazon_FPS_Model_Token instance
     */
    public function withFriendlyName($value)
    {
        $this->setFriendlyName($value);
        return $this;
    }


    /**
     * Checks if FriendlyName is set
     * 
     * @return bool true if FriendlyName  is set
     */
    public function isSetFriendlyName()
    {
        return !is_null($this->_fields['FriendlyName']['FieldValue']);
    }

    /**
     * Gets the value of the TokenStatus property.
     * 
     * @return string TokenStatus
     */
    public function getTokenStatus() 
    {
        return $this->_fields['TokenStatus']['FieldValue'];
    }

    /**
     * Sets the value of the TokenStatus property.
     * 
     * @param string TokenStatus
     * @return this instance
     */
    public function setTokenStatus($value) 
    {
        $this->_fields['TokenStatus']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the TokenStatus and returns this instance
     * 
     * @param string $value TokenStatus
     * @return Amazon_FPS_Model_Token instance
     */
    public function withTokenStatus($value)
    {
        $this->setTokenStatus($value);
        return $this;
    }


    /**
     * Checks if TokenStatus is set
     * 
     * @return bool true if TokenStatus  is set
     */
    public function isSetTokenStatus()
    {
        return !is_null($this->_fields['TokenStatus']['FieldValue']);
    }

    /**
     * Gets the value of the DateInstalled property.
     * 
     * @return string DateInstalled
     */
    public function getDateInstalled() 
    {
        return $this->_fields['DateInstalled']['FieldValue'];
    }

    /**
     * Sets the value of the DateInstalled property.
     * 
     * @param string DateInstalled
     * @return this instance
     */
    public function setDateInstalled($value) 
    {
        $this->_fields['DateInstalled']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the DateInstalled and returns this instance
     * 
     * @param string $value DateInstalled
     * @return Amazon_FPS_Model_Token instance
     */
    public function withDateInstalled($value)
    {
        $this->setDateInstalled($value);
        return $this;
    }


    /**
     * Checks if DateInstalled is set
     * 
     * @return bool true if DateInstalled  is set
     */
    public function isSetDateInstalled()
    {
        return !is_null($this->_fields['DateInstalled']['FieldValue']);
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
     * @return Amazon_FPS_Model_Token instance
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
     * Gets the value of the TokenType property.
     * 
     * @return string TokenType
     */
    public function getTokenType() 
    {
        return $this->_fields['TokenType']['FieldValue'];
    }

    /**
     * Sets the value of the TokenType property.
     * 
     * @param string TokenType
     * @return this instance
     */
    public function setTokenType($value) 
    {
        $this->_fields['TokenType']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the TokenType and returns this instance
     * 
     * @param string $value TokenType
     * @return Amazon_FPS_Model_Token instance
     */
    public function withTokenType($value)
    {
        $this->setTokenType($value);
        return $this;
    }


    /**
     * Checks if TokenType is set
     * 
     * @return bool true if TokenType  is set
     */
    public function isSetTokenType()
    {
        return !is_null($this->_fields['TokenType']['FieldValue']);
    }

    /**
     * Gets the value of the OldTokenId property.
     * 
     * @return string OldTokenId
     */
    public function getOldTokenId() 
    {
        return $this->_fields['OldTokenId']['FieldValue'];
    }

    /**
     * Sets the value of the OldTokenId property.
     * 
     * @param string OldTokenId
     * @return this instance
     */
    public function setOldTokenId($value) 
    {
        $this->_fields['OldTokenId']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the OldTokenId and returns this instance
     * 
     * @param string $value OldTokenId
     * @return Amazon_FPS_Model_Token instance
     */
    public function withOldTokenId($value)
    {
        $this->setOldTokenId($value);
        return $this;
    }


    /**
     * Checks if OldTokenId is set
     * 
     * @return bool true if OldTokenId  is set
     */
    public function isSetOldTokenId()
    {
        return !is_null($this->_fields['OldTokenId']['FieldValue']);
    }

    /**
     * Gets the value of the PaymentReason property.
     * 
     * @return string PaymentReason
     */
    public function getPaymentReason() 
    {
        return $this->_fields['PaymentReason']['FieldValue'];
    }

    /**
     * Sets the value of the PaymentReason property.
     * 
     * @param string PaymentReason
     * @return this instance
     */
    public function setPaymentReason($value) 
    {
        $this->_fields['PaymentReason']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the PaymentReason and returns this instance
     * 
     * @param string $value PaymentReason
     * @return Amazon_FPS_Model_Token instance
     */
    public function withPaymentReason($value)
    {
        $this->setPaymentReason($value);
        return $this;
    }


    /**
     * Checks if PaymentReason is set
     * 
     * @return bool true if PaymentReason  is set
     */
    public function isSetPaymentReason()
    {
        return !is_null($this->_fields['PaymentReason']['FieldValue']);
    }




}