<?php
namespace Omnipay\Rotessa\Model;

use Omnipay\Rotessa\Object\Country;
use Omnipay\Rotessa\Object\Address;
use Omnipay\Rotessa\Object\CustomerType;
use Omnipay\Rotessa\Model\ModelInterface;
use Omnipay\Rotessa\Object\BankAccountType;
use Omnipay\Rotessa\Object\AuthorizationType;
use Omnipay\Rotessa\Exception\ValidationException;

class CustomerPatchModel extends CustomerModel implements ModelInterface {
    
    protected $required = ["id","custom_identifier","name","email","customer_type","home_phone","phone","bank_name","institution_number","transit_number","bank_account_type","authorization_type","routing_number","account_number","address"];

}
