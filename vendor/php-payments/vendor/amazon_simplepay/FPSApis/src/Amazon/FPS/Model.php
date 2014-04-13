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
 * Amazon_FPS_Model - base class for all model classes
 */ 
abstract class Amazon_FPS_Model
{
    
    /** @var array */
    protected  $_fields = array ();
          
    /**
     * Construct new model class
     * 
     * @param mixed $data - DOMElement or Associative Array to construct from. 
     */
    public function __construct($data = null)
    {
        if (!is_null($data)) {
            if ($this->_isAssociativeArray($data)) {
                $this->_fromAssociativeArray($data);
            } elseif ($this->_isDOMElement($data)) {
                $this->_fromDOMElement($data);
            } else {
                throw new Exception ("Unable to construct from provided data. 
                                Please be sure to pass associative array or DOMElement");
            }
            
        }
    }

    /**
     * Support for virtual properties getters. 
     * 
     * Virtual property call example:
     *  
     *   $action->Property
     *   
     * Direct getter(preferred): 
     * 
     *   $action->getProperty()      
     * 
     * @param string $propertyName name of the property
     */
    public function __get($propertyName)
    {
       $getter = "get$propertyName"; 
       return $this->$getter();
    }

    /**
     * Support for virtual properties setters. 
     * 
     * Virtual property call example:
     *  
     *   $action->Property  = 'ABC'
     *   
     * Direct setter (preferred):
     * 
     *   $action->setProperty('ABC')     
     * 
     * @param string $propertyName name of the property
     */
    public function __set($propertyName, $propertyValue)
    {
       $setter = "set$propertyName";
       $this->$setter($propertyValue);
       return $this;
    }

         
    /**
     * XML fragment representation of this object
     * Note, name of the root determined by caller 
     * This fragment returns inner fields representation only
     * @return string XML fragment for this object
     */
    protected function _toXMLFragment() 
    {
        $xml = "";
        foreach ($this->_fields as $fieldName => $field) {
            $fieldValue = $field['FieldValue'];
            if (!is_null($fieldValue)) {
                $fieldType = $field['FieldType'];
                if (is_array($fieldType)) {
                    if ($this->_isComplexType($fieldType[0])) {
                        foreach ($fieldValue as $item) {
                            $xml .= "<$fieldName>";
                            $xml .= $item->_toXMLFragment();
                            $xml .= "</$fieldName>";
                        }
                    } else {
                        foreach ($fieldValue as $item) {
                            $xml .= "<$fieldName>";
                            $xml .= $this->_escapeXML($item);
                            $xml .= "</$fieldName>";
                        }
                    }
                } else {
                    if ($this->_isComplexType($fieldType)) {
                        $xml .= "<$fieldName>";
                        $xml .= $fieldValue->_toXMLFragment();
                        $xml .= "</$fieldName>";
                    } else {
                        $xml .= "<$fieldName>";
                        $xml .= $this->_escapeXML($fieldValue);
                        $xml .= "</$fieldName>";
                    }
                }
            }
        }
        return $xml;
    }


    /**
     * Escape special XML characters
     * @return string with escaped XML characters
     */
    private function _escapeXML($str) 
    {
        $from = array( "&", "<", ">", "'", "\""); 
        $to = array( "&amp;", "&lt;", "&gt;", "&#039;", "&quot;");
        return str_replace($from, $to, $str); 
    }


    
    /**
     * Construct from DOMElement 
     * 
     * This function iterates over object fields and queries XML 
     * for corresponding tag value. If query succeeds, value extracted 
     * from xml, and field value properly constructed based on field type. 
     *
     * Field types defined as arrays always constructed as arrays,
     * even if XML contains a single element - to make sure that
     * data structure is predictable, and no is_array checks are
     * required.
     * 
     * @param DOMElement $dom XML element to construct from
     */
    private function _fromDOMElement(DOMElement $dom)
    {
        $xpath = new DOMXPath($dom->ownerDocument);
        $xpath->registerNamespace('a', 'http://fps.amazonaws.com/doc/2008-09-17/');
        
        foreach ($this->_fields as $fieldName => $field) {
            $fieldType = $field['FieldType'];   
            if (is_array($fieldType)) {
                if ($this->_isComplexType($fieldType[0])) {
                    $elements = $xpath->query("./a:$fieldName", $dom);
                    if ($elements->length >= 1) {
                        require_once (str_replace('_', DIRECTORY_SEPARATOR, $fieldType[0]) . ".php");
                        foreach ($elements as $element) {
                            $this->_fields[$fieldName]['FieldValue'][] = new $fieldType[0]($element);
                        }
                    } 
                } else {
                    $elements = $xpath->query("./a:$fieldName", $dom);
                    if ($elements->length >= 1) {
                        foreach ($elements as $element) {
                            $text = $xpath->query('./text()', $element);
                            $this->_fields[$fieldName]['FieldValue'][] = $text->item(0)->data;
                        }
                    }  
                }
            } else {
                if ($this->_isComplexType($fieldType)) {
                    $elements = $xpath->query("./a:$fieldName", $dom);
                    if ($elements->length == 1) {
                        require_once (str_replace('_', DIRECTORY_SEPARATOR, $fieldType) . ".php");
                        $this->_fields[$fieldName]['FieldValue'] = new $fieldType($elements->item(0));
                    }   
                } else {
                    $element = $xpath->query("./a:$fieldName/text()", $dom);
                    if ($element->length == 1) {
                        $this->_fields[$fieldName]['FieldValue'] = $element->item(0)->data;
                    }
                }
            }
        }
    }


    /**
     * Construct from Associative Array
     * 
     * 
     * @param array $array associative array to construct from
     */
    private function _fromAssociativeArray(array $array)
    {
        foreach ($this->_fields as $fieldName => $field) {
            $fieldType = $field['FieldType'];   
            if (is_array($fieldType)) {
                if ($this->_isComplexType($fieldType[0])) {
                    if (array_key_exists($fieldName, $array)) { 
                        $elements = $array[$fieldName];
                        if (!$this->_isNumericArray($elements)) {
                            $elements =  array($elements);    
                        }
                        if (count ($elements) >= 1) {
                            require_once (str_replace('_', DIRECTORY_SEPARATOR, $fieldType[0]) . ".php");
                            foreach ($elements as $element) {
                                $this->_fields[$fieldName]['FieldValue'][] = new $fieldType[0]($element);
                            }
                        }
                    } 
                } else {
                    if (array_key_exists($fieldName, $array)) {
                        $elements = $array[$fieldName];
                        if (!$this->_isNumericArray($elements)) {
                            $elements =  array($elements);    
                            }
                        if (count ($elements) >= 1) {
                            foreach ($elements as $element) {
                                $this->_fields[$fieldName]['FieldValue'][] = $element;
                            }
                        }  
                    }
                }
            } else {
                if ($this->_isComplexType($fieldType)) {
                    if (array_key_exists($fieldName, $array)) {
                        require_once (str_replace('_', DIRECTORY_SEPARATOR, $fieldType) . ".php");
                        $this->_fields[$fieldName]['FieldValue'] = new $fieldType($array[$fieldName]);
                    }   
                } else {
                    if (array_key_exists($fieldName, $array)) {
                        $this->_fields[$fieldName]['FieldValue'] = $array[$fieldName];
                    }
                }
            }
        }
    }



    /**
     * Determines if field is complex type
     * 
     * @param string $fieldType field type name
     */
    private function _isComplexType ($fieldType) 
    {
        return preg_match('/^Amazon_FPS_Model_/', $fieldType);
    }

   /**
    * Checks  whether passed variable is an associative array
    *
    * @param mixed $var
    * @return TRUE if passed variable is an associative array
    */
    private function _isAssociativeArray($var) {
        return is_array($var) && array_keys($var) !== range(0, sizeof($var) - 1);
    }

   /**
    * Checks  whether passed variable is DOMElement
    *
    * @param mixed $var
    * @return TRUE if passed variable is DOMElement
    */
    private function _isDOMElement($var) {
        return $var instanceof DOMElement;
    }

   /**
    * Checks  whether passed variable is numeric array
    *
    * @param mixed $var
    * @return TRUE if passed variable is an numeric array
    */
    protected function _isNumericArray($var) {
        return is_array($var) && array_keys($var) === range(0, sizeof($var) - 1);
    }
}
