<?php
namespace Omnipay\Rotessa;

use Omnipay\Common\AbstractGateway;
use Omnipay\Rotessa\ClientInterface;
use Omnipay\Rotessa\Message\RequestInterface;

abstract class AbstractClient extends AbstractGateway implements ClientInterface
{
  
  protected $default_parameters = [];
  
  public function getDefaultParameters() : array {
    return  $this->default_parameters;
  }

  public function setDefaultParameters(array $params) { 
    $this->default_parameters = $params;
  }
  
}
