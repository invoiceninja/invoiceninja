<?php
namespace Omnipay\Rotessa\Message\Request;
// You will need to create this BaseRequest class as abstracted from the AbstractRequest; 
use Omnipay\Rotessa\Message\Request\BaseRequest;
use Omnipay\Rotessa\Message\Request\RequestInterface;

class PatchCustomersId extends BaseRequest implements RequestInterface
{
  
  protected $endpoint = '/customers/{id}';
  protected $method = 'PATCH';
  protected static $model = 'CustomerPatch';
  
    public function setId(string $value) {
    $this->setParameter('id',$value);  
  }
    public function setCustomIdentifier(string $value) {
    $this->setParameter('custom_identifier',$value);  
  }
    public function setName(string $value) {
    $this->setParameter('name',$value);  
  }
    public function setEmail(string $value) {
    $this->setParameter('email',$value);  
  }
    public function setCustomerType(string $value) {
    $this->setParameter('customer_type',$value);  
  }
    public function setHomePhone(string $value) {
    $this->setParameter('home_phone',$value);  
  }
    public function setPhone(string $value) {
    $this->setParameter('phone',$value);  
  }
    public function setBankName(string $value) {
    $this->setParameter('bank_name',$value);  
  }
    public function setInstitutionNumber(string $value) {
    $this->setParameter('institution_number',$value);  
  }
    public function setTransitNumber(string $value) {
    $this->setParameter('transit_number',$value);  
  }
    public function setBankAccountType(string $value) {
    $this->setParameter('bank_account_type',$value);  
  }
    public function setAuthorizationType(string $value) {
    $this->setParameter('authorization_type',$value);  
  }
    public function setRoutingNumber(string $value) {
    $this->setParameter('routing_number',$value);  
  }
    public function setAccountNumber(string $value) {
    $this->setParameter('account_number',$value);  
  }
    public function setAddress(array $value) {
    $this->setParameter('address',$value);  
  }
    public function setTransactionSchedules(array $value) {
    $this->setParameter('transaction_schedules',$value);  
  }
    public function setFinancialTransactions(array $value) {
    $this->setParameter('financial_transactions',$value);  
  }
  }
