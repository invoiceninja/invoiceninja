<?php
namespace Omnipay\Rotessa\Object; 

use Omnipay\Common\ParametersTrait;

final class Address implements \JsonSerializable {

    use ParametersTrait;

    protected $attributes = [
                    "address_1" => "string", 
                    "address_2" => "string", 
                    "city" => "string", 
                    "id" => "int", 
                    "postal_code" => "string", 
                    "province_code" => "string", 
                    "country" => "string"
            ];

    protected $required = ["address_1","address_2","city","postal_code","province_code",];

    public function jsonSerialize() {
        return array_intersect_key($this->getParameters(), array_flip($this->required));
    }

    public function getCountry() : string {
        return $this->getParameter('country');
    }

    public function initialize(array $parameters) {
        foreach($this->attributes as $param => $type) {
            $value = @$parameters[$param] ;
            settype($value, $type);
            $value = $value ?? null;
            $this->parameters->set($param, $value);
        }
    }

    public function __toArray() : array {
        return $this->getParameters();
    }

    public function __toString() : string {
        return $this->getFullAddress();
    }

    public function getFullAddress() :string {
        $full_address = $this->getParameters();
        extract($full_address);

        return "$address_1 $address_2, $city, $postal_code $province_code, $country";
    }
}
