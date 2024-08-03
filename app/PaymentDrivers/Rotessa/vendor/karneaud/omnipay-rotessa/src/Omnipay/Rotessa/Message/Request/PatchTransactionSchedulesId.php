<?php
namespace Omnipay\Rotessa\Message\Request;
// You will need to create this BaseRequest class as abstracted from the AbstractRequest; 
use Omnipay\Rotessa\Message\Request\BaseRequest;
use Omnipay\Rotessa\Message\Request\RequestInterface;

class PatchTransactionSchedulesId extends BaseRequest implements RequestInterface
{
  
  protected $endpoint = '/transaction_schedules/{id}';
  protected $method = 'PATCH';
  
    public function setId(int $value) {
    $this->setParameter('id',$value);  
  }
    public function setAmount($value) {
    $this->setParameter('amount',$value);  
  }
    public function setComment(string $value) {
    $this->setParameter('comment',$value);  
  }
  }
