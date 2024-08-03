<?php
namespace Omnipay\Rotessa\Model;

use Omnipay\Rotessa\Object\Country;
use Omnipay\Rotessa\Object\Address;
use Omnipay\Rotessa\Model\BaseModel;
use Omnipay\Rotessa\Object\CustomerType;
use Omnipay\Rotessa\Model\ModelInterface;
use Omnipay\Rotessa\Object\BankAccountType;
use Omnipay\Rotessa\Object\AuthorizationType;
use Omnipay\Rotessa\Exception\ValidationException;

class CustomerModel extends BaseModel implements ModelInterface {
    
    protected $attributes = [
                    "id" => "string", 
                    "custom_identifier" => "string", 
                    "name" => "string", 
                    "email" => "string", 
                    "customer_type" => "string", 
                    "home_phone" => "string", 
                    "phone" => "string", 
                    "bank_name" => "string", 
                    "institution_number" => "string", 
                    "transit_number" => "string", 
                    "bank_account_type" => "string", 
                    "authorization_type" => "string", 
                    "routing_number" => "string", 
                    "account_number" => "string", 
                    "address" => "object", 
                    "transaction_schedules" => "array", 
                    "financial_transactions" => "array",
                    "active" => "bool"
            ];
            
	protected $defaults = ["active" => false,"customer_type" =>'Business',"bank_account_type" =>'Savings',"authorization_type" =>'Online',];
    protected $required = ["name","email","customer_type","home_phone","phone","bank_name","institution_number","transit_number","bank_account_type","authorization_type","routing_number","account_number","address",'custom_identifier'];

    public function validate() : bool {
       try {
            $country = $this->address->country;
            if(!self::isValidCountry($country)) throw new \Exception("Invalid country!");

            $this->required = array_diff($this->required, Country::isAmerican($country) ? ["institution_number", "transit_number"] : ["bank_account_type", "routing_number"]);
            parent::validate();
            if(Country::isCanadian($country) ) {
                if(!self::isValidTransitNumber($this->getParameter('transit_number'))) throw new \Exception("Invalid transit number!");
                if(!self::isValidInstitutionNumber($this->getParameter('institution_number'))) throw new \Exception("Invalid institution number!");
            }
            if(!self::isValidCustomerType($this->getParameter('customer_type'))) throw new \Exception("Invalid customer type!");
            if(!self::isValidBankAccountType($this->getParameter('bank_account_type'))) throw new \Exception("Invalid bank account type!");
            if(!self::isValidAuthorizationType($this->getParameter('authorization_type'))) throw new \Exception("Invalid authorization type!");
        } catch (\Throwable $th) {
            throw new ValidationException($th->getMessage());
        }

        return true;
    }

    public static function isValidCountry(string $country ) : bool {
       return Country::isValidCountryCode($country) || Country::isValidCountryName($country);
    }

    public static function isValidTransitNumber(string $value ) : bool {
        return strlen($value) == 5;
    }

    public static function isValidInstitutionNumber(string $value ) : bool {
        return strlen($value) == 3;
    }

    public static function isValidCustomerType(string $value ) : bool {
        return  CustomerType::isValid($value);
    }

    public static function isValidBankAccountType(string $value ) : bool {
        return  BankAccountType::isValid($value);
    }

    public static function isValidAuthorizationType(string $value ) : bool {
        return AuthorizationType::isValid($value);
    }

    public function toArray() : array {
        return [ 'address' => (array) $this->getParameter('address') ] + parent::toArray();
    }

    public function jsonSerialize() : array {
        $address = (array) $this->getParameter('address');
        unset($address['country']);

        return  compact('address') + parent::jsonSerialize();
    }
}
