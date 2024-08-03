<?php
namespace Omnipay\Rotessa\Message\Request;
// You will need to create this BaseRequest class as abstracted from the AbstractRequest; 
use Omnipay\Rotessa\Message\Request\BaseRequest;
use Omnipay\Rotessa\Message\Request\RequestInterface;

class PostCustomersShowWithCustomIdentifier extends BaseRequest implements RequestInterface
{
  
  protected $endpoint = '/customers/show_with_custom_identifier';
  protected $method = 'POST';
  protected static $model = null;


  public function setCustomIdentifier(string $value) {
    $this->setParameter('custom_identifier',$value);  
  }

}
