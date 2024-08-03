<?php

namespace Omnipay\Rotessa\Model;

use Omnipay\Common\ParametersTrait;
use Omnipay\Rotessa\Model\ModelInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Omnipay\Rotessa\Exception\ValidationException;

abstract class AbstractModel implements ModelInterface {
    
    use ParametersTrait;

    abstract public function jsonSerialize() : array;
    
    public function validate() : bool {
        $required = array_diff_key( array_flip($this->required), array_filter($this->getParameters()) );
        if(!empty($required)) throw new ValidationException("Could not validate " . implode(",", array_keys($required)) );

        return true;
    }

    public function __get($key) {
        return array_key_exists($key, $this->attributes) ? $this->getParameter($key) : null;
    }

    public function __set($key, $value) {
        if(array_key_exists($key, $this->attributes)) $this->setParameter($key, $value);
    }

    public function __toString() : string {
        return json_encode($this); 
    }

    public function toString() : string {
        return $this->__toString();
    }
    
    public function __toArray() : array {
        return $this->getParameters();
    }


    public function toArray() : array {
        return $this->__toArray();
    }

    public function initialize(array $params = []) {
        $this->parameters = new ParameterBag;
        $parameters = array_merge($this->defaults, $params);
        if ($parameters) {
            foreach ($this->attributes as $param => $type) {
                $value = @$parameters[$param];
                if($value){
                    settype($value, $type);
                    $this->setParameter($param, $value);
                }
            }
        }

        return $this;
    }
}
