<?php
namespace Omnipay\Rotessa\Message\Request;

use Omnipay\Rotessa\Message\Request\RequestInterface;

class PostTransactionSchedulesCreateWithCustomIdentifier extends PostTransactionSchedules implements RequestInterface
{
  
  protected $endpoint = '/transaction_schedules/create_with_custom_identifier';
  protected $method = 'POST';

  public function setCustomIdentifier(string $value) {
    $this->setParameter('custom_identifier',$value);  
  }

}
