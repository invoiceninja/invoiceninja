<?php

namespace Omnipay\Rotessa\Model;

use \DateTime;
use Omnipay\Rotessa\Model\AbstractModel;
use Omnipay\Rotessa\Model\ModelInterface;

class BaseModel extends AbstractModel implements ModelInterface {
    
    protected $attributes = [
        "id" => "string"
    ];
    protected $required = ['id'];
    protected $defaults = ['id' => 0 ];
    
    public function __construct($parameters = array()) {
        $this->initialize($parameters);
    }
   
    public function jsonSerialize() : array {
        return array_intersect_key($this->toArray(), array_flip($this->required) );
    }
}
