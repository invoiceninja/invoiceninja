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
 * Amazon_FPS_Model_VerifySignatureRequest
 * 
 * Properties:
 * <ul>
 * 
 * <li>UrlEndPoint: string</li>
 * <li>HttpParameters: string</li>
 *
 * </ul>
 */ 
class Amazon_FPS_Model_VerifySignatureRequest extends Amazon_FPS_Model
{


    /**
     * Construct new Amazon_FPS_Model_VerifySignatureRequest
     * 
     * @param mixed $data DOMElement or Associative Array to construct from. 
     * 
     * Valid properties:
     * <ul>
     * 
     * <li>UrlEndPoint: string</li>
     * <li>HttpParameters: string</li>
     *
     * </ul>
     */
    public function __construct($data = null)
    {
        $this->_fields = array (
        'UrlEndPoint' => array('FieldValue' => null, 'FieldType' => 'string'),
        'HttpParameters' => array('FieldValue' => null, 'FieldType' => 'string'),
        );
        parent::__construct($data);
    }

        /**
     * Gets the value of the UrlEndPoint property.
     * 
     * @return string UrlEndPoint
     */
    public function getUrlEndPoint() 
    {
        return $this->_fields['UrlEndPoint']['FieldValue'];
    }

    /**
     * Sets the value of the UrlEndPoint property.
     * 
     * @param string UrlEndPoint
     * @return this instance
     */
    public function setUrlEndPoint($value) 
    {
        $this->_fields['UrlEndPoint']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the UrlEndPoint and returns this instance
     * 
     * @param string $value UrlEndPoint
     * @return Amazon_FPS_Model_VerifySignatureRequest instance
     */
    public function withUrlEndPoint($value)
    {
        $this->setUrlEndPoint($value);
        return $this;
    }


    /**
     * Checks if UrlEndPoint is set
     * 
     * @return bool true if UrlEndPoint  is set
     */
    public function isSetUrlEndPoint()
    {
        return !is_null($this->_fields['UrlEndPoint']['FieldValue']);
    }

    /**
     * Gets the value of the HttpParameters property.
     * 
     * @return string HttpParameters
     */
    public function getHttpParameters() 
    {
        return $this->_fields['HttpParameters']['FieldValue'];
    }

    /**
     * Sets the value of the HttpParameters property.
     * 
     * @param string HttpParameters
     * @return this instance
     */
    public function setHttpParameters($value) 
    {
        $this->_fields['HttpParameters']['FieldValue'] = $value;
        return $this;
    }

    /**
     * Sets the value of the HttpParameters and returns this instance
     * 
     * @param string $value HttpParameters
     * @return Amazon_FPS_Model_VerifySignatureRequest instance
     */
    public function withHttpParameters($value)
    {
        $this->setHttpParameters($value);
        return $this;
    }


    /**
     * Checks if HttpParameters is set
     * 
     * @return bool true if HttpParameters  is set
     */
    public function isSetHttpParameters()
    {
        return !is_null($this->_fields['HttpParameters']['FieldValue']);
    }




}