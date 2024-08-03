<?php
namespace Omnipay\Rotessa\Message\Request;
// You will need to create this BaseRequest class as abstracted from the AbstractRequest; 
use Omnipay\Rotessa\Message\Request\BaseRequest;
use Omnipay\Rotessa\Message\Request\RequestInterface;

class DeleteTransactionSchedulesId extends BaseRequest implements RequestInterface
{
  
  protected $endpoint = '/transaction_schedules/{id}';
  protected $method = 'DELETE';
  protected static $model = '';
  
  public function setId(string $value) {
    $this->setParameter('id',$value);  
  }

}
