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
 * Amazon_FPS_Model_VerifySignatureResult
 * 
 * Properties:
 * <ul>
 * 
 * <li>VerificationStatus: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_VerifySignatureResult extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_VerifySignatureResult
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>VerificationStatus: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'VerificationStatus' => array('FieldValue' => null, 'FieldType' => 'string'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the VerificationStatus property.
     * 
     * @return string VerificationStatus
     */
    public function getVerificationStatus() 
    {
        return $this->_fields['VerificationStatus']['FieldValue'];
    }

    /**
     * Sets the value of the VerificationStatus property.
     * 
     * @param string VerificationStatus
     * @return this instance
     */
    public function setVerificationStatus($value) 
    {
        $this->_fields['VerificationStatus']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the VerificationStatus and returns this instance
     * 
     * @param string $value VerificationStatus
     * @return Amazon_FPS_Model_VerifySignatureResult instance
     */
    public function withVerificationStatus($value)
    {
        $this->setVerificationStatus($value);
        return $this;
    }


    /**
     * Checks if VerificationStatus is set
     * 
     * @return bool true if VerificationStatus  is set
     */
    public function isSetVerificationStatus()
    {
        return !is_null($this->_fields['VerificationStatus']['FieldValue']);
    }




}