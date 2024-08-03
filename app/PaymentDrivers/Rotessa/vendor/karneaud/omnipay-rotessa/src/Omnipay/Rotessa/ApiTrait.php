<?php
namespace Omnipay\Rotessa;

use Omnipay\Rotessa\Message\Request\RequestInterface;

trait ApiTrait
{
      public function getCustomers() : RequestInterface {
    return $this->createRequest('GetCustomers', [] );  
  }
    public function postCustomers(array $params) : RequestInterface {
    return $this->createRequest('PostCustomers', $params );  
  }
        public function getCustomersId(array $params) : RequestInterface {
    return $this->createRequest('GetCustomersId', $params );  
  }
    public function patchCustomersId(array $params) : RequestInterface {
    return $this->createRequest('PatchCustomersId', $params );  
  }
        public function postCustomersShowWithCustomIdentifier(array $params) : RequestInterface {
    return $this->createRequest('PostCustomersShowWithCustomIdentifier', $params );  
  }
        public function getTransactionSchedulesId(array $params) : RequestInterface {
    return $this->createRequest('GetTransactionSchedulesId', $params );  
  }
    public function deleteTransactionSchedulesId(array $params) : RequestInterface {
    return $this->createRequest('DeleteTransactionSchedulesId', $params );  
  }
    public function patchTransactionSchedulesId(array $params) : RequestInterface {
    return $this->createRequest('PatchTransactionSchedulesId', $params );  
  }
        public function postTransactionSchedules(array $params) : RequestInterface {
    return $this->createRequest('PostTransactionSchedules', $params );  
  }
        public function postTransactionSchedulesCreateWithCustomIdentifier(array $params) : RequestInterface {
    return $this->createRequest('PostTransactionSchedulesCreateWithCustomIdentifier', $params );  
  }
        public function postTransactionSchedulesUpdateViaPost(array $params) : RequestInterface {
    return $this->createRequest('PostTransactionSchedulesUpdateViaPost', $params );  
  }
     }
